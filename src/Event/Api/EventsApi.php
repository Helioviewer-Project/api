<?php

namespace Helioviewer\Api\Event\Api;

use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

/**
 * EventsApi Client
 *
 * HTTP client for the Helioviewer Events API service.
 * Handles fetching solar events (HEK, CCMC, RHESSI) with coordinate rotation,
 * batch observations for movie frames, event distributions for timelines,
 * and event range queries.
 *
 * All methods capture errors to Sentry and throw EventsApiException on failure.
 */
class EventsApi implements EventsApiInterface {

    /** Known event sources */
    public const VALID_SOURCES = ['HEK', 'CCMC', 'RHESSI'];

    private ClientInterface $client;
    private SentryClientInterface $sentry;

    /**
     * Filter an array of source names to only valid ones.
     *
     * @param string[] $sources
     * @return string[] Only sources that exist in VALID_SOURCES
     */
    public static function filterSources(array $sources): array
    {
        return array_values(array_intersect($sources, self::VALID_SOURCES));
    }

    /**
     * EventsApi constructor.
     * Creates an HTTP client configured with the Events API base URL and timeouts.
     * Falls back to defaults if HV_EVENTS_API_URL or HV_EVENTS_API_TIMEOUT are not defined.
     *
     * @param ClientInterface|null $client Optional Guzzle client for testing
     * @param SentryClientInterface|null $sentry Optional Sentry client for testing
     */
    public function __construct(ClientInterface $client = null, SentryClientInterface $sentry = null)
    {
        $timeout = defined('HV_EVENTS_API_TIMEOUT') ? HV_EVENTS_API_TIMEOUT : 10;
        $connectTimeout = 2;
        $baseUrl = defined('HV_EVENTS_API_URL') ? HV_EVENTS_API_URL : 'https://events.helioviewer.org';
        $this->client = $client ?? new Client([
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Helioviewer-API/2.0'
            ]
        ]);
        $this->sentry = $sentry ?? Sentry::$client;

        $this->sentry->setContext('EventsApi', [
            'api_url' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
        ]);
    }

    /** {@inheritdoc} */
    public function getEventsForSourceLegacy(DateTimeInterface $observationTime, string $source): array
    {
        $formattedTime = $observationTime->format('Y-m-d H:i:s');
        $encodedTime = urlencode($formattedTime);

        $url = "/helioviewer/events/{$source}/observation/{$encodedTime}";

        $this->sentry->setContext('EventsApi', [
            'endpoint' => $url,
        ]);

        try {
            $response = $this->client->request('GET', $url);
            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->sentry->setContext('EventsApi', [
                'error' => $e->getMessage(),
            ]);
            $exception = new EventsApiException("Failed to fetch events for source: " . $e->getMessage(), 0, $e);
            $this->sentry->capture($exception);
            throw $exception;
        }
    }

    /** {@inheritdoc} */
    public function getEventsInRange(int $fromTimestamp, int $toTimestamp, array $paths): array
    {
        $url = "/helioviewer/events/from/{$fromTimestamp}/to/{$toTimestamp}";

        $this->sentry->setContext('EventsApi', [
            'endpoint' => $url,
            'paths' => $paths
        ]);

        try {
            $response = $this->client->request('POST', $url, [
                'json' => ['paths' => $paths]
            ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->sentry->setContext('EventsApi', [
                'error' => $e->getMessage(),
            ]);
            $exception = new EventsApiException("Failed to fetch events: " . $e->getMessage(), 0, $e);
            $this->sentry->capture($exception);
            throw $exception;
        }
    }

    /** {@inheritdoc} */
    public function getDistributions(string $size, int $fromTimestamp, int $toTimestamp, array $paths): array
    {
        $url = "/helioviewer/distributions/size/{$size}/from/{$fromTimestamp}/to/{$toTimestamp}";

        $this->sentry->setContext('EventsApi', [
            'endpoint' => $url,
            'paths' => $paths
        ]);

        try {
            $response = $this->client->request('POST', $url, [
                'json' => ['paths' => $paths]
            ]);

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            $this->sentry->setContext('EventsApi', [
                'error' => $e->getMessage(),
            ]);
            $exception = new EventsApiException("Failed to fetch distributions: " . $e->getMessage(), 0, $e);
            $this->sentry->capture($exception);
            throw $exception;
        }
    }

    /** {@inheritdoc} */
    public function getEventsBatch(array $timestamps, array $sources): array
    {
        // Only allow known sources
        $validSources = self::filterSources($sources);
        if (empty($validSources)) {
            throw new EventsApiException("No valid sources given. Valid sources: " . implode(', ', self::VALID_SOURCES));
        }
        if (empty($timestamps)) {
            return [];
        }

        $sourcesParam = implode('::', $validSources);
        $chunks = array_chunk($timestamps, 150);
        $url = "/helioviewer/events/{$sourcesParam}/observations";

        // Closure to fetch a single chunk of timestamps
        $fetchChunk = function (array $chunkTimestamps) use ($url) {
            $this->sentry->setContext('EventsApi', [
                'endpoint' => $url,
                'timestamp_count' => count($chunkTimestamps),
            ]);

            try {
                $response = $this->client->request('POST', $url, [
                    'json' => ['timestamps' => $chunkTimestamps]
                ]);
                return $this->parseResponse($response);
            } catch (\Exception $e) {
                $this->sentry->setContext('EventsApi', [
                    'error' => $e->getMessage(),
                ]);
                $exception = new EventsApiException("Failed to fetch batch events: " . $e->getMessage(), 0, $e);
                $this->sentry->capture($exception);
                throw $exception;
            }
        };

        // First chunk returns full response (event_types + events + observations)
        $merged = $fetchChunk($chunks[0]);

        // Subsequent chunks only add new observations (event_types and events are the same)
        for ($i = 1; $i < count($chunks); $i++) {
            $chunk = $fetchChunk($chunks[$i]);
            $merged['observations'] += $chunk['observations'];
        }

        // Convert deduplicated response to legacy format per timestamp
        $result = [];
        foreach ($merged['observations'] as $timestamp => $obs) {
            $result[$timestamp] = $this->batchToLegacy(
                $merged['event_types'],
                $merged['events'],
                $obs
            );
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
    private function batchToLegacy(array $eventTypes, array $events, array $obs): array
    {
        $categories = [];

        foreach ($eventTypes as $et) {
            $groups = [];

            foreach ($et['groups'] as $group) {
                $data = [];

                foreach ($group['event_ids'] as $eventId) {
                    // Event not active at this timestamp
                    if (!isset($obs[$eventId])) continue;

                    $event = $events[$eventId] ?? null;
                    if (!$event) continue;

                    $coords = $obs[$eventId];

                    // Merge static event data with rotated coordinates
                    $legacyEvent = $event;
                    $legacyEvent['hv_hpc_x'] = $coords['hv_hpc_x'];
                    $legacyEvent['hv_hpc_y'] = $coords['hv_hpc_y'];
                    $legacyEvent['hv_hpc_x_final'] = $coords['hv_hpc_x'];
                    $legacyEvent['hv_hpc_y_final'] = $coords['hv_hpc_y'];

                    // Shift footprint polygon by the same rotation delta as the center point
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

    /**
     * Parse the HTTP response body as JSON.
     * Validates that the response is valid JSON and returns an array.
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array Decoded JSON response
     * @throws \RuntimeException if JSON decoding fails or response is not an array
     */
    private function parseResponse($response): array
    {
        $body = (string)$response->getBody();
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->sentry->setContext('EventsApi', [
                'raw_response' => $body,
                'json_error' => json_last_error_msg(),
                'response_status' => $response->getStatusCode()
            ]);
            throw new \RuntimeException("Failed to decode JSON response: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            $this->sentry->setContext('EventsApi', [
                'unexpected_response_type' => gettype($data),
                'raw_response' => $body,
                'response_status' => $response->getStatusCode()
            ]);
            throw new \RuntimeException("Unexpected response format: expected array, got " . gettype($data));
        }

        return $data;
    }
}
