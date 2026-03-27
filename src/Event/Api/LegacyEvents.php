<?php

namespace Helioviewer\Api\Event\Api;

/**
 * Converts the deduplicated batch events response into legacy event_categories format.
 * Takes the batch response structure (event_types, events, observations)
 * and produces per-timestamp arrays matching getEventsForSourceLegacy output.
 */
class LegacyEvents implements LegacyEventsInterface
{
    /**
     * Convert the full batch response to legacy format keyed by timestamp.
     *
     * @param array $batchResponse Raw response with event_types, events, observations
     * @return array Keyed by timestamp, each value is legacy-format event categories
     */
    public function convertAll(array $batchResponse): array
    {
        $result = [];
        $eventTypes = $batchResponse['event_types'] ?? [];
        $events = $batchResponse['events'] ?? [];
        $observations = $batchResponse['observations'] ?? [];

        foreach ($observations as $timestamp => $obs) {
            $result[$timestamp] = $this->convert($eventTypes, $events, $obs);
        }

        return $result;
    }

    /**
     * Convert one timestamp's batch data to legacy event_categories format.
     * Merges static event data with per-timestamp rotated coordinates,
     * shifts footprint polygons by the rotation delta,
     * and rebuilds the category/group/data hierarchy.
     *
     * @param array $eventTypes Category/group structure with event_ids references
     * @param array $events     Static event data keyed by event ID
     * @param array $obs        Rotated coordinates keyed by event ID for this timestamp
     * @return array Legacy format: [{pin, name, groups: [{name, data: [...]}]}]
     */
    public function convert(array $eventTypes, array $events, array $obs): array
    {
        $categories = [];

        foreach ($eventTypes as $et) {
            $groups = [];

            foreach ($et['groups'] as $group) {
                $data = [];

                foreach ($group['event_ids'] as $eventId) {
                    if (!isset($obs[$eventId])) continue;

                    $event = $events[$eventId] ?? null;
                    if (!$event) continue;

                    $coords = $obs[$eventId];

                    $legacyEvent = $event;
                    $legacyEvent['hv_hpc_x'] = $coords['hv_hpc_x'];
                    $legacyEvent['hv_hpc_y'] = $coords['hv_hpc_y'];
                    $legacyEvent['hv_hpc_x_final'] = $coords['hv_hpc_x'];
                    $legacyEvent['hv_hpc_y_final'] = $coords['hv_hpc_y'];

                    $dx = $coords['hv_hpc_x'] - $event['hv_hpc_x'];
                    $dy = $coords['hv_hpc_y'] - $event['hv_hpc_y'];
                    if (!empty($event['footprint'])) {
                        $legacyEvent['footprint'] = array_map(
                            fn($p) => ['x' => $p['x'] + $dx, 'y' => $p['y'] + $dy],
                            $event['footprint']
                        );
                    }

                    $data[] = $legacyEvent;
                }

                if (!empty($data)) {
                    $groups[] = ['name' => $group['name'], 'data' => $data];
                }
            }

            if (!empty($groups)) {
                $categories[] = [
                    'pin' => $et['pin'],
                    'name' => $et['name'],
                    'groups' => $groups
                ];
            }
        }

        return $categories;
    }
}
