<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Timeline;

use Helioviewer\Api\Event\Api\EventsApiInterface;

/**
 * Coverage strategy for minute-level resolution.
 *
 * Calls the Events API events-in-range endpoint which returns individual events.
 * Groups them by event_type, clamps their times to the extended range,
 * and delegates to SwimLaner for vertical stacking of overlapping events.
 *
 * Output format matches what the frontend timeline expects:
 * [{data: [event+x+x2+y, ...], event_type: 'AR', res: 'm', showInLegend: true}]
 */
class MinuteCoverage implements CoverageInterface
{
    private SwimLaner $swimLaner;

    public function __construct(SwimLaner $swimLaner)
    {
        $this->swimLaner = $swimLaner;
    }

    /**
     * Fetch individual events from the Events API, group by type, and layout with swim lanes.
     *
     * @param EventsApiInterface $eventsApi API client
     * @param TimeRange $range Extended time range
     * @param array $paths Selection paths (e.g. ['HEK>>Active Region'])
     * @param string $resolution Always 'm' for this strategy
     * @return array Array of series with swim-laned events
     */
    public function execute(EventsApiInterface $eventsApi, TimeRange $range, array $paths, string $resolution): array
    {
        // Nothing selected — return empty
        if (empty($paths)) {
            return [];
        }

        // Fetch individual events from the Events API using extended range
        $response = $eventsApi->getEventsInRange($range->extendedStartSec(), $range->extendedEndSec(), $paths);
        $events = $response['events'] ?? [];

        // Group events by event_type
        $results = [];
        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? 'UNK';

            // Create series for this event type if not seen yet
            if (!isset($results[$eventType])) {
                $results[$eventType] = [
                    'data' => [],
                    'event_type' => $eventType,
                    'res' => 'm',
                    'showInLegend' => false
                ];
            }

            // Convert event start/end times to milliseconds
            $timeStart = strtotime($event['event_starttime']) * 1000;
            $timeEnd = strtotime($event['event_endtime']) * 1000;

            // Show in legend only if event overlaps the visible range
            if ($timeEnd >= $range->start() && $timeStart <= $range->end()) {
                $results[$eventType]['showInLegend'] = true;
            }

            // Clamp event times to the extended range
            if ($range->extendedStart() > $timeStart) {
                $timeStart = $range->extendedStart();
            }
            if ($range->extendedEnd() < $timeEnd) {
                $timeEnd = $range->extendedEnd();
            }

            // Add timeline coordinates to the event
            $event['x'] = $timeStart;
            $event['x2'] = $timeEnd;
            $event['y'] = 0;

            $results[$eventType]['data'][] = $event;
        }

        // Assign y values so overlapping events stack vertically
        $results = $this->swimLaner->assign($results);

        return array_values($results);
    }
}
