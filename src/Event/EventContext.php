<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

use Helioviewer\Api\Event\Api\EventsApiInterface;
use Helioviewer\Api\Sentry\Sentry;

/**
 * Lazy aggregate of "all events the renderer needs, per date".
 *
 * Built via build() (one EventsApi::getEventsForFramesWithSelections call,
 * whose reply arrives in chunks), then queried per-frame via
 * getEventsForDate($date). The renderer never sees EventsStateManager,
 * never sees EventsApi, never sees the raw upstream wire shape -- it just
 * receives draw-ready event dicts.
 *
 * Memory model: build() DOES NOT materialize per-frame shifted footprints,
 * and it does not keep the reply as decoded either. Each chunk of the reply
 * is packed as it arrives (see absorb()): an event becomes an EventRecord,
 * its footprint one string of doubles per ring, and an observation 17 bytes
 * (dx, dy, the far-side flag). A month of events with 1.6 million footprint
 * points is 70 MB this way against 839 MB as PHP arrays, and only one
 * decoded chunk is in memory at a time. getEventsForDate() shapes exactly one
 * frame's list on each call and throws it away, so live memory stays
 * proportional to a single frame. Callers must not accumulate the returned
 * list across frames -- iterate, render, discard.
 *
 * Each event dict returned by getEventsForDate() has the shape:
 *   [
 *     'id'        => '019c3d8f-...',
 *     'label'     => 'AR 13700',       // empty string '' when labels are hidden for this source
 *     'type'      => 'AR',
 *     'pin'       => 'AR',
 *     'visible'   => true,             // false = event is behind the sun this frame (dim the pin)
 *     'hv_hpc_x'  => -119.0,           // rotated for this frame
 *     'hv_hpc_y'  => 570.2,
 *     'footprint' => [[{x,y,visible?}, ...], ...],  // list of rings; every point already shifted by
 *                                                   // (dx,dy). A point carries visible=false only when
 *                                                   // it is behind the sun; the key is absent otherwise,
 *                                                   // mirroring the wire shape so the renderer's far-side
 *                                                   // test stays a strict === false.
 *   ]
 *
 * NOTE: there is no separate label_visibility flag. The renderer treats an
 * empty 'label' as "do not draw any label text", so we apply the visibility
 * decision at shape time by zeroing out hidden labels.
 */
class EventContext
{
    private static ?self $emptyInstance = null;

    /**
     * @param array<string, true>          $requestedTimestamps  Set of timestamps passed to build(),
     *                                                           used to distinguish "no events at this
     *                                                           frame" (expected) from "caller asked
     *                                                           for a date we never fetched" (bug).
     * @param array<string, EventRecord>   $events               The reply's 'events' block, packed: uuid => record.
     * @param array<string, array<string, string>> $observations The reply's 'timestamps' block, packed:
     *                                                           ts => uuid => 17 bytes (see packObservation).
     * @param array<string, mixed>         $visibilitySelections Per-source visibility flags (label_visibility only,
     *                                                           marker_visibility is filtered at build() time).
     */
    private function __construct(
        private array $requestedTimestamps = [],
        private array $events = [],
        private array $observations = [],
        private array $visibilitySelections = [],
        private array $rawSelections = [],
        private array $rawVisibilitySelections = []
    ) {
    }

    /**
     * Memoized empty context. Use this when you need an EventContext-shaped
     * placeholder without an actual fetch (e.g. a renderer fallback when the
     * caller forgot to inject one).
     */
    public static function empty(): self
    {
        return self::$emptyInstance ??= new self();
    }

    /**
     * Build the context by fetching events for $frameTimestamps filtered by $selections.
     *
     * Empty $frameTimestamps OR empty $selections -> no HTTP call, empty context.
     * HTTP failure -> Sentry capture, returns an empty context.
     *
     * @param string[] $frameTimestamps      dates (one per movie/screenshot frame) to fetch events for
     * @param string[] $selections           path-prefix selections (from EventsStateManager::getSelections())
     * @param array    $visibilitySelections per-source visibility map (from EventsStateManager::getVisibilitySelections())
     * @param EventsApiInterface $api
     * @param int      $chunkSize            forwarded to the API client
     * @param string   $logLabel             forwarded to the API client (per-chunk error_log tag)
     */
    public static function build(
        array $frameTimestamps,
        array $selections,
        array $visibilitySelections,
        EventsApiInterface $api,
        int $chunkSize = 50,
        string $logLabel = ''
    ): self {
        // Remember what the client asked for before the marker_visibility filter
        // below mutates $selections. This is the blob we persist so a later
        // reTakeScreenshot / movie reQueue reproduces the identical render.
        $rawSelections           = $selections;
        $rawVisibilitySelections = $visibilitySelections;

        // For each source whose marker_visibility is explicitly false, drop
        // its "SOURCE>>..." selections. Missing / non-false → assume visible.
        foreach ($visibilitySelections as $source => $flags) {
            if (($flags['marker_visibility'] ?? true) === false) {
                $selections = array_values(array_filter(
                    $selections,
                    fn(string $p) => !str_starts_with($p, $source . '>>')
                ));
            }
        }

        $requestedTimestampsSet = array_fill_keys($frameTimestamps, true);

        // Short-circuit when there's nothing to fetch. requestedTimestamps still
        // remembers the caller's set so getEventsForDate() returns [] for known
        // dates without triggering the "unrequested date" Sentry signal.
        if (empty($frameTimestamps) || empty($selections)) {
            return new self(
                $requestedTimestampsSet,
                rawSelections: $rawSelections,
                rawVisibilitySelections: $rawVisibilitySelections
            );
        }

        // Each chunk of the reply is packed into these two blocks the moment
        // it is decoded, then dropped. Chunks arrive in order and an event or
        // a timestamp seen twice keeps its first form, as merging the raw
        // chunks with + did.
        $events = [];
        $observations = [];
        $absorb = function (array $chunk) use (&$events, &$observations) {
            foreach ($chunk['events'] ?? [] as $uuid => $data) {
                if (!isset($events[$uuid])) {
                    $events[$uuid] = EventRecord::fromWire($data);
                }
            }
            foreach ($chunk['timestamps'] ?? [] as $ts => $obs) {
                if (!isset($observations[$ts])) {
                    $observations[$ts] = array_map(self::packObservation(...), $obs);
                }
            }
        };

        try {
            // A client that hands the chunks to $absorb returns []; one that
            // returns the whole merged reply instead (a fake) is absorbed here.
            $raw = $api->getEventsForFramesWithSelections(
                $frameTimestamps, $selections, $chunkSize, $logLabel, $absorb
            );
            if ($raw) {
                $absorb($raw);
            }
        } catch (\Throwable $e) {
            Sentry::setContext('EventContext', [
                'frame_timestamps_count' => count($frameTimestamps),
                'selections'             => $selections,
                'log_label'              => $logLabel,
            ]);
            Sentry::capture($e);
            if ($logLabel !== '') {
                error_log(sprintf('[%s] events fetch failed, the frames get no events: %s', $logLabel, $e->getMessage()));
            }
            return new self(
                $requestedTimestampsSet,
                rawSelections: $rawSelections,
                rawVisibilitySelections: $rawVisibilitySelections
            );
        }

        return new self(
            $requestedTimestampsSet,
            $events,
            $observations,
            $visibilitySelections,
            $rawSelections,
            $rawVisibilitySelections
        );
    }

    /**
     * One observation of the reply's 'timestamps' block ({dx, dy, visible?})
     * as 17 bytes: the two doubles, then '0' when the event is behind the sun
     * at that frame (visible === false) and '1' otherwise.
     */
    private static function packObservation(array $coords): string
    {
        return pack('dd', (float) ($coords['dx'] ?? 0.0), (float) ($coords['dy'] ?? 0.0))
            . ((($coords['visible'] ?? true) !== false) ? '1' : '0');
    }

    /**
     * Returns the draw-ready event list for $date, or [] if none.
     *
     * Computes on demand: every call walks the observations for $date and
     * builds fresh event dicts (with rotation-shifted footprints). Nothing is
     * cached across calls, so live memory stays bounded to one frame.
     *
     * Invariant: every timestamp passed to build() is remembered in
     * $requestedTimestamps. If the caller asks for a date that was NOT part of
     * that set (and the context isn't the empty singleton), that's a
     * programming bug -- we Sentry-log and return [] without blowing up render.
     */
    public function getEventsForDate(string $date): array
    {
        if (!isset($this->requestedTimestamps[$date])) {
            if (!empty($this->requestedTimestamps)) {
                Sentry::setContext('EventContext', [
                    'requested_date'  => $date,
                    'available_dates' => array_keys($this->requestedTimestamps),
                ]);
                Sentry::message("EventContext::getEventsForDate called with a date that wasn't part of build()");
            }
            return [];
        }

        $obs = $this->observations[$date] ?? [];
        if (empty($obs) || empty($this->events)) {
            return [];
        }

        $list = [];
        foreach ($obs as $eventId => $packed) {
            $event = $this->events[$eventId] ?? null;
            if (!$event) {
                continue;
            }

            // Default to true: when the source isn't in the visibility map,
            // assume labels are on.
            $labelVisible = (bool) ($this->visibilitySelections[$event->source()]['label_visibility'] ?? true);

            // Rotation delta from canonical to this frame, delivered by the
            // events API per observation, and the far-side flag of the event.
            ['dx' => $dx, 'dy' => $dy] = unpack('ddx/ddy', $packed);
            $visible = $packed[16] !== '0';

            $list[] = [
                'id'        => $eventId,
                'label'     => $labelVisible ? $event->label() : '',
                'type'      => $event->type(),
                'pin'       => $event->pin(),
                // Per-frame event-level visibility from the observations block:
                // false = the event is behind the sun this frame (dim the pin).
                'visible'   => $visible,
                'hv_hpc_x'  => $event->hpcX() + $dx,
                'hv_hpc_y'  => $event->hpcY() + $dy,
                'footprint' => $event->hasFootprint() ? $event->footprintShifted($dx, $dy) : [],
            ];
        }
        return $list;
    }

    /**
     * Whether any timestamp had at least one observation referencing a known
     * event. Cheap: doesn't materialize any footprints.
     */
    public function hasEvents(): bool
    {
        if (empty($this->events)) {
            return false;
        }
        foreach ($this->observations as $obs) {
            if (!empty($obs)) {
                return true;
            }
        }
        return false;
    }

    /**
     * The persist blob: the raw event_selections + event_visibility_selections
     * that produced this context, exactly as the client sent them (before the
     * marker_visibility filter). Written to the screenshots/movies row so a
     * cache-miss retake or reQueue rebuilds the same render.
     */
    public function exportEventsStateBlob(): string
    {
        return json_encode([
            'event_selections'            => $this->rawSelections,
            'event_visibility_selections' => $this->rawVisibilitySelections,
        ]);
    }
}
