<?php declare(strict_types=1);

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

    /**
     * Fetch events for multiple observation timestamps in batched requests.
     * Returns legacy format keyed by timestamp.
     *
     * @param string[] $timestamps Array of observation datetime strings
     * @param string[] $sources Array of source names (e.g. ['HEK', 'CCMC', 'RHESSI'])
     * @param int $chunkSize Max timestamps per upstream POST request
     * @param string $logLabel Optional label prepended to per-chunk error_log lines (e.g. "Movie:Xp66n")
     * @return array Keyed by timestamp, each value is legacy-format event categories
     * @throws EventsApiException on API errors or unexpected responses
     */
    public function getEventsBatch(array $timestamps, array $sources, int $chunkSize = 50, string $logLabel = ''): array;

    /**
     * Fetch events for multiple observation timestamps filtered by a list of
     * path-prefix selections. Posts to /helioviewer/events/frames_with_selections.
     *
     * Returns the RAW merged response (no legacy conversion):
     *   [
     *     'events'     => [ <uuid> => {path, label, start, end, hv_hpc_x, hv_hpc_y, footprint, type, pin} ],
     *     'timestamps' => [ <ts>   => { <uuid> => {hv_hpc_x, hv_hpc_y} } ],
     *   ]
     *
     * Timestamps are paginated by $chunkSize (capped at the upstream limit of
     * 150). Selections are sent verbatim with every request (limit: 200).
     *
     * @param string[] $timestamps Array of observation datetime strings
     * @param string[] $selections Path-prefix strings (e.g. ['HEK>>Flare', 'CCMC>>DONKI>>CME']); max 200
     * @param int $chunkSize Max timestamps per upstream POST request (capped at 150)
     * @param string $logLabel Optional label prepended to per-chunk error_log lines
     * @return array Merged raw response with 'events' and 'timestamps' keys
     * @throws EventsApiException on API errors, empty selections, or selections > 200
     */
    public function getEventsForFramesWithSelections(
        array $timestamps,
        array $selections,
        int $chunkSize = 50,
        string $logLabel = ''
    ): array;
}
