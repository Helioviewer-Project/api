<?php
/**
 * EventsTimeline Class Definition
 * Encapsulates event coverage timeline queries and data processing
 *
 * @category Event
 * @package  Helioviewer
 * @author   Kasim Necdet Percinel <kasim.n.oercinel@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Event;

use DateTime;
use DateInterval;
use DatePeriod;
use InvalidArgumentException;
use Exception;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiInterface;

require_once HV_ROOT_DIR . '/../src/Helper/HelioviewerEvents.php';

class EventsTimeline
{
    // Threshold (ms) => resolution mapping for timeline display
    private const RESOLUTION_THRESHOLDS = [
        86400000 => 'm',       // < 1 day
        172800000 => '30m',    // < 2 days
        864000000 => 'h',      // < 10 days
        16070400000 => 'D',    // < ~6 months
        40176000000 => 'W',    // < ~15 months
        157680000000 => 'M',   // < ~5 years
    ];

    private \Helper_HelioviewerEvents $events;
    private EventSelections $eventSelections;
    private EventsApiInterface $eventsApi;
    private int $startMs;     // milliseconds
    private int $endMs;       // milliseconds
    private int $currentMs;   // milliseconds
    private string $resolution;

    /**
     * @throws InvalidArgumentException If parameters are invalid
     */
    public function __construct(string $eventLayers, $startTimestamp, $endTimestamp, $currentTimestamp, ?EventsApiInterface $eventsApi = null)
    {
        $this->eventsApi = $eventsApi ?? new EventsApi();
        // Validate all three timestamps are provided and positive integers (in milliseconds)
        $startTimestamp = filter_var($startTimestamp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $endTimestamp = filter_var($endTimestamp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $currentTimestamp = filter_var($currentTimestamp, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($startTimestamp === false || $endTimestamp === false || $currentTimestamp === false) {
            throw new InvalidArgumentException('startTimestamp, endTimestamp, and currentTimestamp must be positive integer timestamps in milliseconds');
        }

        // Validate chronological order: start <= end
        if ($startTimestamp > $endTimestamp) {
            throw new InvalidArgumentException('startTimestamp must be less than or equal to endTimestamp');
        }

        $this->startMs = $startTimestamp;
        $this->endMs = $endTimestamp;
        $this->currentMs = $currentTimestamp;

        $this->resolution = current(array_filter(self::RESOLUTION_THRESHOLDS, fn($t) => $this->endMs - $this->startMs < $t, ARRAY_FILTER_USE_KEY)) ?: 'Y';
        $this->events = new \Helper_HelioviewerEvents($eventLayers);
        $this->eventSelections = EventSelections::buildFromLegacyEventStrings($eventLayers);
    }

    public function getResolution(): string { return $this->resolution; }
    public function getStartMs(): int { return $this->startMs; }
    public function getEndMs(): int { return $this->endMs; }
    public function getCurrentMs(): int { return $this->currentMs; }
    public function getEventSelections(): EventSelections { return $this->eventSelections; }

    public function timeline(): string
    {
        if ($this->resolution === 'm') {
            return $this->getMinuteCoverage();
        }
        return $this->getAggregatedCoverage();
    }

    /**
     * Get aggregated event coverage using EventsApi distributions endpoint
     */
    public function getAggregatedCoverage(): string
    {
        // Original request range (in milliseconds)
        $startMs = $this->startMs;
        $endMs = $this->endMs;

        // Expand range by distance on both sides for smooth scrolling buffer
        $distance = $endMs - $startMs;
        $extendedStartMs = $startMs - $distance;
        $extendedEndMs = $endMs + $distance;

        // Convert to seconds for API call
        $extendedStartSec = intval($extendedStartMs / 1000);
        $extendedEndSec = intval($extendedEndMs / 1000);

        // Get selection paths from EventSelections
        $paths = iterator_to_array($this->eventSelections);

        if (empty($paths)) {
            return json_encode([]);
        }

        $results = [];

        // Call the Events API
        $response = $this->eventsApi->getDistributions(
            $this->resolution,
            $extendedStartSec,
            $extendedEndSec,
            $paths
        );

        $event_types = $response['event_types'] ?? [];
        $buckets = $response['buckets'] ?? [];

        foreach ($event_types as $et) {
            $results[$et] = [
                'data' => [],
                'event_type' => $et,
                'res' => $this->resolution,
                'showInLegend' => true,
            ];
        }

        foreach ($buckets as $bucket) {
            $bucketStartMs = $bucket['start'] * 1000;
            $default_counts = array_fill_keys($event_types, 0);

            $counts = $bucket['counts'] ?? [];
            $counts = array_merge($default_counts, $counts);

            foreach ($counts as $eventType => $count) {
                $results[$eventType]['data'][] = [$bucketStartMs, (int) $count];
            }
        }

        ksort($results);
        return json_encode(array_values($results));
    }

    /**
     * Get minute-level event coverage using EventsApi events endpoint
     */
    public function getMinuteCoverage(): string
    {
        // Calculate extended time range (3x visible range for smooth scrolling)
        $distance = $this->endMs - $this->startMs;
        $extendedStartMs = $this->startMs - $distance;
        $extendedEndMs = $this->endMs + $distance;

        // Convert to seconds for API call
        $fromTimestamp = intval($extendedStartMs / 1000);
        $toTimestamp = intval($extendedEndMs / 1000);

        // Get selection paths from EventSelections
        $paths = iterator_to_array($this->eventSelections);

        // Fetch events from the Events API
        $response = $this->eventsApi->getEventsInRange($fromTimestamp, $toTimestamp, $paths);
        $events = $response['events'] ?? [];

        // Group events by event_type and add x, x2 for timeline display
        $results = [];

        foreach ($events as $event) {
            $eventType = $event['event_type'] ?? 'UNK';

            // If this event_type not seen yet, create a new group
            if (!isset($results[$eventType])) {
                $results[$eventType] = [
                    'data' => [],
                    'event_type' => $eventType,
                    'res' => 'm',
                    'showInLegend' => true
                ];
            }

            // Convert event times to milliseconds for x and x2
            $timeStart = strtotime($event['event_starttime']) * 1000;
            $timeEnd = strtotime($event['event_endtime']) * 1000;

            // Clamp to extended range
            if ($extendedStartMs > $timeStart) {
                $timeStart = $extendedStartMs;
            }
            if ($extendedEndMs < $timeEnd) {
                $timeEnd = $extendedEndMs;
            }

            // Add x and x2 to event
            $event['x'] = $timeStart;
            $event['x2'] = $timeEnd;
            $event['y'] = 0; // Temporary, will be set by swim lane algorithm

            // Add event to the data array for this event_type
            $results[$eventType]['data'][] = $event;
        }

        // SWIM LANE ALGORITHM - Stack overlapping events
        // Events that overlap in time are placed in different "swim lanes" (y values)
        $i = 1;       // Next available swim lane number
        $levels = []; // Tracks events in each lane: [lane => [events...]]

        foreach ($results as $eventType => $series) {
            $data = [];

            foreach ($series['data'] as $event) {
                $placed = false;

                // Try to fit in an existing lane
                foreach ($levels as $row => $rowEvents) {
                    $last = end($rowEvents);
                    // If new event starts after last event ends, it fits here
                    if ($event['x'] >= $last['x2']) {
                        $event['y'] = $row;
                        $levels[$row][] = $event;
                        $data[] = $event;
                        $placed = true;
                        break;
                    }
                }

                // No existing lane works, create a new one
                if (!$placed) {
                    $levels[$i] = [$event];
                    $event['y'] = $i;
                    $data[] = $event;
                    $i++;
                }
            }

            $results[$eventType]['data'] = $data;
        }

        // Convert to indexed array
        return json_encode(array_values($results));
    }
}
