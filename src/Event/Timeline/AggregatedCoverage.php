<?php

namespace Helioviewer\Api\Event\Timeline;

use Helioviewer\Api\Event\Api\EventsApiInterface;

/**
 * Coverage strategy for non-minute resolutions (30m, h, D, W, M, Y).
 *
 * Calls the Events API distributions endpoint which returns pre-bucketed
 * event counts per time period. Each bucket has a start time and counts
 * per event type.
 *
 * Output format matches what the frontend timeline expects:
 * [{data: [[timestamp_ms, count], ...], event_type: 'AR', res: 'h', showInLegend: true}]
 */
class AggregatedCoverage implements CoverageInterface
{
    /**
     * Fetch distribution buckets from the Events API and format for the timeline.
     *
     * @param EventsApiInterface $eventsApi API client
     * @param TimeRange $range Extended time range
     * @param array $paths Selection paths (e.g. ['HEK>>Active Region', 'CCMC>>DONKI'])
     * @param string $resolution Bucket size: 30m, h, D, W, M, Y
     * @return array Array of series, each with data points as [timestamp_ms, count]
     */
    public function execute(EventsApiInterface $eventsApi, TimeRange $range, array $paths, string $resolution): array
    {
        // Nothing selected — return empty
        if (empty($paths)) {
            return [];
        }

        // Call the Events API for bucketed counts
        $response = $eventsApi->getDistributions(
            $resolution,
            $range->extendedStartSec(),
            $range->extendedEndSec(),
            $paths
        );

        $eventTypes = $response['event_types'] ?? [];
        $buckets = $response['buckets'] ?? [];

        // Initialize a series for each event type
        $results = [];
        foreach ($eventTypes as $et) {
            $results[$et] = [
                'data' => [],
                'event_type' => $et,
                'res' => $resolution,
                'showInLegend' => true,
            ];
        }

        // Fill in data points from each bucket
        foreach ($buckets as $bucket) {
            // Convert bucket start time from seconds to milliseconds
            $bucketStartMs = $bucket['start'] * 1000;

            // Default all event types to 0 for this bucket
            $defaultCounts = array_fill_keys($eventTypes, 0);

            // Merge actual counts over the defaults (missing types get 0)
            $counts = array_merge($defaultCounts, $bucket['counts'] ?? []);

            // Add a data point [timestamp_ms, count] to each event type's series
            foreach ($counts as $eventType => $count) {
                $results[$eventType]['data'][] = [$bucketStartMs, (int) $count];
            }
        }

        // Sort by event type name and return as indexed array
        ksort($results);
        return array_values($results);
    }
}
