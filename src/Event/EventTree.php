<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

/**
 * Buckets a flat list of events (the shape returned by
 * EventsApi::getEventsForSource()) into a "SOURCE>>Label" tree, using
 * EventSelections::$event_types_map as the canonical catalogue.
 *
 * Usage:
 *   $tree    = EventTree::make($flatEvents, $requestedSources);
 *   $payload = $tree->export();     // -> ['HEK>>Active Region' => [...], ...]
 *
 * make() also pings Sentry once per call if it saw:
 *   - paths that didn't match any pre-seeded "SOURCE>>Label" bucket
 *     (likely a new HEK/CCMC/RHESSI concept the map doesn't know yet --
 *     these events still get bucketed under a dynamically-created key);
 *   - malformed/empty paths -- these events are dropped from the output.
 */
class EventTree
{
    /** @var array<string, list<array<string, mixed>>> bucket name => events */
    private array $buckets;

    private function __construct(array $buckets)
    {
        $this->buckets = $buckets;
    }

    /**
     * @param list<array<string, mixed>>  $events  Flat events from EventsApi
     * @param list<string>                $sources Sources to seed buckets for;
     *                                             only these contribute the
     *                                             "SOURCE>>Label" pre-seeded keys.
     * @param SentryClientInterface|null  $sentry  Optional client override for
     *                                             tests. Falls back to the
     *                                             static Sentry::$client.
     */
    public static function make(array $events, array $sources, ?SentryClientInterface $sentry = null): self
    {
        $sentry  = $sentry ?? Sentry::$client;
        $buckets = [];

        // Pre-seed every known bucket for the requested sources.
        foreach ($sources as $source) {
            if (!isset(EventSelections::$event_types_map[$source])) {
                continue;
            }
            foreach (EventSelections::$event_types_map[$source] as $code => $label) {
                $buckets[$source . '>>' . $label] = [];
            }
        }

        $unknownPaths = [];
        $invalidPaths = [];

        foreach ($events as $event) {
            $path  = $event['path'] ?? '';
            $parts = explode('>>', $path);

            // Drop events whose path can't yield a SOURCE>>Label bucket.
            if (count($parts) < 2 || $parts[0] === '' || $parts[1] === '') {
                $invalidPaths[] = $path;
                continue;
            }

            $matched = false;
            foreach (array_keys($buckets) as $bucket) {
                if (str_starts_with($path, $bucket . '>>')) {
                    $buckets[$bucket][] = $event;
                    $matched = true;
                    break;
                }
            }

            if (!$matched) {
                $bucket = $parts[0] . '>>' . $parts[1];
                $unknownPaths[] = $path;
                if (!isset($buckets[$bucket])) {
                    $buckets[$bucket] = [];
                }
                $buckets[$bucket][] = $event;
            }
        }

        if (!empty($unknownPaths) || !empty($invalidPaths)) {
            $sentry->setContext('SimpleTreeUnknownPaths', [
                'unknown_paths'     => array_values(array_unique($unknownPaths)),
                'invalid_paths'     => array_values(array_unique($invalidPaths)),
                'requested_sources' => $sources,
            ]);
            $sentry->message(
                'simpletree: encountered paths not in EventSelections::$event_types_map'
            );
        }

        return new self($buckets);
    }

    /**
     * Returns the bucketed structure ready to be JSON-encoded as the
     * HTTP response body.
     *
     * @return array<string, list<array<string, mixed>>>
     */
    public function export(): array
    {
        return $this->buckets;
    }
}
