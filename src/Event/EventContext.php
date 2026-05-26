<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

use Helioviewer\Api\Event\Api\EventsApiInterface;
use Helioviewer\Api\Sentry\Sentry;

/**
 * Lazy aggregate of "all events the renderer needs, per date".
 *
 * Built eagerly via build() (single HTTP call to
 * EventsApi::getEventsForFramesWithSelections), then queried per-frame
 * via getEventsForDate($date). The renderer never sees EventsStateManager,
 * never sees EventsApi, never sees the raw upstream wire shape -- it just
 * receives draw-ready event dicts.
 *
 * Each event dict has the shape:
 *   [
 *     'id'        => '019c3d8f-...',
 *     'label'     => 'AR 13700',       // empty string '' when labels are hidden for this source
 *     'type'      => 'AR',
 *     'pin'       => 'AR',
 *     'hv_hpc_x'  => -119.0,           // rotated for this frame
 *     'hv_hpc_y'  => 570.2,
 *     'footprint' => [{x,y}, ...],     // already shifted by (dx,dy)
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
     * @param array<string, array<int, array<string, mixed>>> $eventsByDate
     *      date => list of event dicts.
     *      Invariant: every timestamp passed to build() appears as a key here,
     *      even if its list is empty. We rely on this to detect the
     *      "caller asks for an unrequested date" programming-bug case.
     */
    private function __construct(private array $eventsByDate)
    {
    }

    /**
     * Memoized empty context. Use this when you need an EventContext-shaped
     * placeholder without an actual fetch (e.g. a renderer fallback when the
     * caller forgot to inject one).
     */
    public static function empty(): self
    {
        return self::$emptyInstance ??= new self([]);
    }

    /**
     * Build the context by fetching events for $timestamps filtered by $selections.
     *
     * Empty $timestamps OR empty $selections -> no HTTP call, empty context.
     * HTTP failure -> Sentry capture, returns an empty context.
     *
     * @param string[] $timestamps           dates to fetch events for
     * @param string[] $selections           path-prefix selections (from EventsStateManager::getSelections())
     * @param array    $visibilitySelections per-source visibility map (from EventsStateManager::getVisibilitySelections())
     * @param EventsApiInterface $api
     * @param int      $chunkSize            forwarded to the API client
     * @param string   $logLabel             forwarded to the API client (per-chunk error_log tag)
     */
    public static function build(
        array $timestamps,
        array $selections,
        array $visibilitySelections,
        EventsApiInterface $api,
        int $chunkSize = 50,
        string $logLabel = ''
    ): self {
        // Short-circuit when there's nothing to fetch. Pre-populate with empty
        // lists per requested timestamp so the post-build invariant holds:
        // every requested date appears as a key in $eventsByDate.
        if (empty($timestamps) || empty($selections)) {
            return new self(array_fill_keys($timestamps, []));
        }

        try {
            $raw = $api->getEventsForFramesWithSelections(
                $timestamps, $selections, $chunkSize, $logLabel
            );
        } catch (\Throwable $e) {
            Sentry::setContext('EventContext', [
                'timestamps_count' => count($timestamps),
                'selections'       => $selections,
                'log_label'        => $logLabel,
            ]);
            Sentry::capture($e);
            return new self(array_fill_keys($timestamps, []));
        }

        // Shape the raw frames_with_selections response into per-date
        // draw-ready arrays. Footprints get rotation-shifted by (dx,dy).
        // For hidden-label sources we set 'label' to '' so the renderer
        // (which already treats empty label as "skip label drawing") needs
        // no extra logic.
        $events       = $raw['events']     ?? [];
        $observations = $raw['timestamps'] ?? [];
        $eventsByDate = [];

        foreach ($observations as $ts => $obs) {
            $list = [];
            foreach ($obs as $eventId => $coords) {
                $event = $events[$eventId] ?? null;
                if (!$event) {
                    continue;
                }

                // Determine source by parsing the event's canonical path.
                // path is e.g. "HEK>>Active Region>>SPoCA"
                $path   = $event['path'] ?? '';
                $source = explode('>>', $path)[0] ?? '';
                // Default to true: when the source isn't in the visibility map,
                // assume labels are on (matches getVisibilitySelections()'s default).
                $labelVisible = (bool) ($visibilitySelections[$source]['label_visibility'] ?? true);

                // Rotation delta: how much did this frame's rotated coords
                // drift from the event's "canonical" coords?
                $dx = $coords['hv_hpc_x'] - ($event['hv_hpc_x'] ?? 0.0);
                $dy = $coords['hv_hpc_y'] - ($event['hv_hpc_y'] ?? 0.0);

                $footprint = [];
                if (!empty($event['footprint'])) {
                    $footprint = array_map(
                        fn($p) => ['x' => $p['x'] + $dx, 'y' => $p['y'] + $dy],
                        $event['footprint']
                    );
                }

                $type = $event['type'] ?? 'UNK';
                $list[] = [
                    'id'        => $eventId,
                    // Empty label = "do not draw a label". This is how we
                    // encode "labels hidden for this source" downstream.
                    'label'     => $labelVisible ? ($event['label'] ?? '') : '',
                    'type'      => $type,
                    'pin'       => $event['pin'] ?? $type,
                    'hv_hpc_x'  => $coords['hv_hpc_x'],
                    'hv_hpc_y'  => $coords['hv_hpc_y'],
                    'footprint' => $footprint,
                ];
            }
            $eventsByDate[$ts] = $list;
        }

        return new self($eventsByDate);
    }

    /**
     * Returns the draw-ready event list for $date, or [] if none.
     *
     * The upstream guarantees that every timestamp passed to build() ends up
     * as a key in the response (even when its list is empty), so a missing
     * key here when the context has some dates means the caller is asking
     * for a date that was never part of the build() request. That's a
     * programming bug; we Sentry-log and return [] without blowing up the
     * render.
     *
     * If $this->eventsByDate is itself empty (e.g. the EventContext::empty()
     * singleton, or a build() that short-circuited), the "missing key" case
     * is expected — no Sentry signal.
     */
    public function getEventsForDate(string $date): array
    {
        if (!array_key_exists($date, $this->eventsByDate)) {
            if (!empty($this->eventsByDate)) {
                Sentry::setContext('EventContext', [
                    'requested_date'  => $date,
                    'available_dates' => array_keys($this->eventsByDate),
                    'events_by_date'  => $this->eventsByDate,
                ]);
                Sentry::message("EventContext::getEventsForDate called with a date that wasn't part of build()");
            }
            return [];
        }
        return $this->eventsByDate[$date];
    }

    /**
     * Whether any timestamp produced at least one event. Convenience for
     * callers that want to short-circuit downstream work.
     */
    public function hasEvents(): bool
    {
        foreach ($this->eventsByDate as $list) {
            if (!empty($list)) {
                return true;
            }
        }
        return false;
    }
}
