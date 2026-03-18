<?php

namespace Helioviewer\Api\Event\Api;

use DateTimeInterface;

interface EventsApiInterface {

    /**
     * Get events for a specific source
     *
     * @param DateTimeInterface $observationTime The observation time
     * @param string $source The data source (e.g. "CCMC")
     * @return array Array of event data
     * @throws EventsApiException on API errors or unexpected responses
     */
    public function getEventsForSourceLegacy(DateTimeInterface $observationTime, string $source): array;

    /**
     * Get events within a time range for given selection paths
     *
     * @param int $fromTimestamp Unix timestamp (seconds) for range start
     * @param int $toTimestamp Unix timestamp (seconds) for range end
     * @param array $paths Array of selection paths (e.g. ["CCMC>>DONKI>>CME", "HEK>>Active Region"])
     * @return array Array of event data
     * @throws EventsApiException on API errors or unexpected responses
     */
    public function getEventsInRange(int $fromTimestamp, int $toTimestamp, array $paths): array;

    /**
     * Get event distributions (counts per time bucket) for given selection paths
     *
     * @param string $size Bucket size: 30m, h, D, W, M, Y
     * @param int $fromTimestamp Unix timestamp (seconds) for range start
     * @param int $toTimestamp Unix timestamp (seconds) for range end
     * @param array $paths Array of selection paths (e.g. ["CCMC>>DONKI>>CME", "HEK>>Flare"])
     * @return array Distribution data with buckets containing counts per event type
     * @throws EventsApiException on API errors or unexpected responses
     */
    public function getDistributions(string $size, int $fromTimestamp, int $toTimestamp, array $paths): array;
}
