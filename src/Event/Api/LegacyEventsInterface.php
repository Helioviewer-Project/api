<?php

namespace Helioviewer\Api\Event\Api;

/**
 * Interface for converting deduplicated batch events response into legacy format.
 */
interface LegacyEventsInterface
{
    /**
     * Convert the full batch response to legacy format keyed by timestamp.
     *
     * @param array $batchResponse Raw response with event_types, events, observations
     * @return array Keyed by timestamp, each value is legacy-format event categories
     */
    public function convertAll(array $batchResponse): array;

    /**
     * Convert one timestamp's batch data to legacy event_categories format.
     *
     * @param array $eventTypes Category/group structure with event_ids references
     * @param array $events     Static event data keyed by event ID
     * @param array $obs        Rotated coordinates keyed by event ID for this timestamp
     * @return array Legacy format: [{pin, name, groups: [{name, data: [...]}]}]
     */
    public function convert(array $eventTypes, array $events, array $obs): array;
}
