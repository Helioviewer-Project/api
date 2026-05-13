<?php declare(strict_types=1);

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
    private LegacyEventsInterface $legacyEvents;

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
     * @param LegacyEventsInterface|null $legacyEvents Optional converter for testing
     */
    public function __construct(ClientInterface $client = null, SentryClientInterface $sentry = null, LegacyEventsInterface $legacyEvents = null)
    {
        $timeout = defined('HV_EVENTS_API_TIMEOUT') ? HV_EVENTS_API_TIMEOUT : 10;
        $connectTimeout = 2;
        $baseUrl = defined('HV_EVENTS_API_URL') ? HV_EVENTS_API_URL : 'https://events.helioviewer.org';

        $options = [
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Helioviewer-API/2.0'
            ]
        ];
        if (defined('HV_PROXY_HOST')) {
            $options['proxy'] = HV_PROXY_HOST;
        }

        $this->client = $client ?? new Client($options);
        $this->sentry = $sentry ?? Sentry::$client;
        $this->legacyEvents = $legacyEvents ?? new LegacyEvents();

        $this->sentry->setContext('EventsApi', [
            'api_url' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            'proxy' => defined('HV_PROXY_HOST') ? HV_PROXY_HOST : null,
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
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
        } catch (\Throwable $e) {
            $this->sentry->setContext('EventsApi', [
                'error' => $e->getMessage(),
            ]);
            $exception = new EventsApiException("Failed to fetch distributions: " . $e->getMessage(), 0, $e);
            $this->sentry->capture($exception);
            throw $exception;
        }
    }

    /** {@inheritdoc} */
    public function getEventsBatch(array $timestamps, array $sources, int $chunkSize = 50, string $logLabel = ''): array
    {
        // Only allow known sources
        $validSources = self::filterSources($sources);
        if (empty($validSources)) {
            throw new EventsApiException("No valid sources given. Valid sources: " . implode(', ', self::VALID_SOURCES));
        }
        if (empty($timestamps)) {
            return [];
        }
        if ($chunkSize < 1) {
            $chunkSize = 50;
        }

        $sourcesParam = implode('::', $validSources);
        $chunks = array_chunk($timestamps, $chunkSize);
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
            } catch (\Throwable $e) {
                $this->sentry->setContext('EventsApi', [
                    'error' => $e->getMessage(),
                ]);
                $exception = new EventsApiException("Failed to fetch batch events: " . $e->getMessage(), 0, $e);
                $this->sentry->capture($exception);
                throw $exception;
            }
        };

        $logChunk = function (int $i, int $total, int $size, int $elapsedMs) use ($logLabel) {
            if ($logLabel === '') {
                return;
            }
            error_log(sprintf(
                "[%s] EventsApi chunk %d/%d (%d timestamps) took %dms",
                $logLabel, $i + 1, $total, $size, $elapsedMs
            ));
        };

        // First chunk returns full response (event_types + events + observations)
        $start = microtime(true);
        $merged = $fetchChunk($chunks[0]);
        $logChunk(0, count($chunks), count($chunks[0]), (int) round((microtime(true) - $start) * 1000));

        // Subsequent chunks only add new observations (event_types and events are the same)
        for ($i = 1; $i < count($chunks); $i++) {
            $start = microtime(true);
            $chunk = $fetchChunk($chunks[$i]);
            $logChunk($i, count($chunks), count($chunks[$i]), (int) round((microtime(true) - $start) * 1000));
            $merged['observations'] += $chunk['observations'];
        }

        // Convert deduplicated response to legacy format per timestamp
        return $this->legacyEvents->convertAll($merged);
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
