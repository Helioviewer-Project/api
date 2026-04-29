<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Timeline;

/**
 * Assigns y (swim lane) values to timeline events for visual stacking.
 *
 * In the timeline visualization, events are drawn as horizontal bars.
 * When two events overlap in time, they need different y positions
 * so they don't draw on top of each other.
 *
 * The algorithm tries to reuse existing lanes:
 * - If an event starts after the last event in a lane ends, it fits in that lane.
 * - If no existing lane works, a new lane is created.
 *
 * This produces a compact vertical layout where non-overlapping events
 * share lanes and overlapping events stack.
 */
class SwimLaner
{
    /**
     * Assign y values to events in each series.
     *
     * Each series has a 'data' array of events with 'x' (start ms) and 'x2' (end ms).
     * After processing, each event also gets a 'y' value indicating its swim lane.
     *
     * @param array $series Array keyed by event type, each with 'data' containing events
     * @return array Same structure with y values assigned to each event
     */
    public function assign(array $series): array
    {
        // Tracks the next available lane number across all event types
        $laneCounter = 1;

        // Tracks which events are in each lane: [laneNumber => [events...]]
        // Used to check if a new event fits in an existing lane
        $levels = [];

        foreach ($series as $eventType => $s) {
            $data = [];

            foreach ($s['data'] as $event) {
                $placed = false;

                // Try to fit this event in an existing lane
                // by checking if it starts after the last event in that lane ends
                foreach ($levels as $row => $rowEvents) {
                    $last = end($rowEvents);
                    if ($event['x'] >= $last['x2']) {
                        // Fits in this lane — reuse it
                        $event['y'] = $row;
                        $levels[$row][] = $event;
                        $data[] = $event;
                        $placed = true;
                        break;
                    }
                }

                // No existing lane works — create a new one
                if (!$placed) {
                    $levels[$laneCounter] = [$event];
                    $event['y'] = $laneCounter;
                    $data[] = $event;
                    $laneCounter++;
                }
            }

            $series[$eventType]['data'] = $data;
        }

        return $series;
    }
}
