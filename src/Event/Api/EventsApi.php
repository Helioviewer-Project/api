<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Api;

use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Pool;
use GuzzleHttp\TransferStats;
use GuzzleHttp\ClientInterface;
use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

/**
 * EventsApi Client
 *
 * HTTP client for the Helioviewer Events API service.
 * Handles fetching solar events (HEK, CCMC, RHESSI, WSA) with coordinate
 * rotation, batched observations with selection filtering for movie frames,
 * event distributions for timelines, and event range queries.
 *
 * All methods capture errors to Sentry and throw EventsApiException on failure.
 */
class EventsApi implements EventsApiInterface {

    /** Known event sources */
    public const VALID_SOURCES = ['HEK', 'CCMC', 'RHESSI', 'WSA'];

    /** Fallback used when the HV_* config constant is not defined. */
    private const DEFAULT_MAX_CHUNK_SIZE = 150;
    private const DEFAULT_MAX_SELECTIONS = 200;

    /**
     * When chunks are fetched in parallel, the timestamps are spread over the
     * concurrent requests, but no chunk is made smaller than this: every reply
     * repeats the events' footprints, so tiny chunks would mostly re-download
     * the same footprints.
     */
    private const MIN_PARALLEL_CHUNK = 10;

    /**
     * ... and no chunk is made larger than this. The events server computes
     * about 0.3 s per timestamp when every event of a source is selected, and
     * its proxy cuts a request off at 60 s: a chunk of 50 took it 30 to 44 s
     * with six in flight, a chunk of 25 about 20 s, for the same movie time
     * (measured on events.helioviewer.org, 300 frames over a day).
     */
    private const MAX_PARALLEL_CHUNK = 25;

    /**
     * Upstream-imposed cap on timestamps per batch request. Driven by
     * HV_EVENTS_API_EVENTS_PER_FRAME_MAX_CHUNK_SIZE in Config.ini.
     */
    public static function maxChunkSize(): int
    {
        return defined('HV_EVENTS_API_EVENTS_PER_FRAME_MAX_CHUNK_SIZE')
            ? (int) HV_EVENTS_API_EVENTS_PER_FRAME_MAX_CHUNK_SIZE
            : self::DEFAULT_MAX_CHUNK_SIZE;
    }

    /**
     * Upstream-imposed cap on selections per frames_with_selections request.
     * Driven by HV_EVENTS_API_EVENTS_PER_FRAME_MAX_SELECTIONS in Config.ini.
     */
    /**
     * How many frames_with_selections chunk requests run at the same time
     * (events_api_events_per_frame_concurrency). 1 = one after another.
     */
    public static function concurrency(): int
    {
        return defined('HV_EVENTS_API_EVENTS_PER_FRAME_CONCURRENCY')
            ? max(1, (int) HV_EVENTS_API_EVENTS_PER_FRAME_CONCURRENCY)
            : 1;
    }

    public static function maxSelections(): int
    {
        return defined('HV_EVENTS_API_EVENTS_PER_FRAME_MAX_SELECTIONS')
            ? (int) HV_EVENTS_API_EVENTS_PER_FRAME_MAX_SELECTIONS
            : self::DEFAULT_MAX_SELECTIONS;
    }

    private ClientInterface $client;
    private SentryClientInterface $sentry;
    private int $concurrency;

    /**
     * EventsApi constructor.
     * Creates an HTTP client configured with the Events API base URL and timeouts.
     * Falls back to defaults if HV_EVENTS_API_URL or HV_EVENTS_API_TIMEOUT are not defined.
     *
     * @param ClientInterface|null $client Optional Guzzle client for testing
     * @param SentryClientInterface|null $sentry Optional Sentry client for testing
     */
    public function __construct(ClientInterface $client = null, SentryClientInterface $sentry = null, ?int $concurrency = null)
    {
        $this->concurrency = max(1, $concurrency ?? self::concurrency());
        $timeout = defined('HV_EVENTS_API_TIMEOUT') ? HV_EVENTS_API_TIMEOUT : 10;
        $connectTimeout = 2;
        $baseUrl = defined('HV_EVENTS_API_URL') ? HV_EVENTS_API_URL : 'https://events.helioviewer.org';

        $options = [
            'base_uri' => $baseUrl,
            'timeout' => $timeout,
            'connect_timeout' => $connectTimeout,
            // Ask for gzip (sends Accept-Encoding: gzip) and decompress
            // transparently. Responses are mostly JSON footprints, about
            // two-thirds smaller compressed; an uncompressed reply still works.
            'decode_content' => 'gzip',
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
    public function getEventsForSource(DateTimeInterface $observationTime, string $source): array
    {
        $formattedTime = $observationTime->format('Y-m-d\TH:i:s\Z');
        $encodedTime = urlencode($formattedTime);

        $url = "/api/v1/events/{$source}/observation/{$encodedTime}";

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
    public function getEventsForFramesWithSelections(
        array $timestamps,
        array $selections,
        int $chunkSize = 50,
        string $logLabel = ''
    ): array {
        if (empty($selections)) {
            throw new EventsApiException("No selections given. At least one path-prefix selection is required.");
        }
        $maxSelections = self::maxSelections();
        if (count($selections) > $maxSelections) {
            throw new EventsApiException("Too many selections: " . count($selections) . ". Upstream limit is " . $maxSelections . ".");
        }
        if (empty($timestamps)) {
            return [];
        }
        if ($chunkSize < 1) {
            $chunkSize = defined('HV_EVENTS_API_EVENTS_PER_FRAME_CHUNKSIZE') ? (int) HV_EVENTS_API_EVENTS_PER_FRAME_CHUNKSIZE : 50;
        }
        $maxChunk = self::maxChunkSize();
        if ($chunkSize > $maxChunk) {
            $chunkSize = $maxChunk;
        }

        $url = "/helioviewer/events/frames_with_selections";

        // In parallel mode, spread the timestamps over the concurrent requests
        // (e.g. 60 timestamps, concurrency 6 -> 6 chunks of 10), never above
        // the requested chunk size or MAX_PARALLEL_CHUNK (300 timestamps ->
        // 12 chunks of 25) and never below MIN_PARALLEL_CHUNK.
        if ($this->concurrency > 1) {
            $chunkSize = min($chunkSize, self::MAX_PARALLEL_CHUNK, max(self::MIN_PARALLEL_CHUNK, (int) ceil(count($timestamps) / $this->concurrency)));
        }
        $chunks = array_chunk($timestamps, $chunkSize);

        $logChunk = function (int $i, int $total, int $size, int $elapsedMs) use ($logLabel) {
            if ($logLabel === '') {
                return;
            }
            error_log(sprintf(
                "[%s] EventsApi frames_with_selections chunk %d/%d (%d timestamps) took %dms",
                $logLabel, $i + 1, $total, $size, $elapsedMs
            ));
        };

        // Merge each chunk's reply into the result in chunk order (events
        // keyed by uuid, timestamps by datetime string; + is a first-wins
        // union), then drop it: every reply repeats the events' footprints, so
        // only the merged result is kept in memory.
        $merged = null;
        $merge = function (array $chunk) use (&$merged) {
            if ($merged === null) {
                $merged = $chunk;
                return;
            }
            $merged['events']     = ($merged['events']     ?? []) + ($chunk['events']     ?? []);
            $merged['timestamps'] = ($merged['timestamps'] ?? []) + ($chunk['timestamps'] ?? []);
        };

        if ($this->concurrency > 1 && count($chunks) > 1) {
            $this->fetchChunksInParallel($url, $chunks, $selections, $merge, $logChunk, $logLabel);
        } else {
            $this->fetchChunksInSequence($url, $chunks, $selections, $merge, $logChunk);
        }

        return $merged;
    }

    /**
     * One frames_with_selections request per chunk, one after another; each
     * reply is handed to $merge as it arrives.
     */
    private function fetchChunksInSequence(string $url, array $chunks, array $selections, callable $merge, callable $logChunk): void
    {
        foreach ($chunks as $i => $chunkTimestamps) {
            $this->sentry->setContext('EventsApi', [
                'endpoint' => $url,
                'timestamp_count' => count($chunkTimestamps),
                'selection_count' => count($selections),
            ]);
            $start = microtime(true);
            try {
                $response = $this->client->request('POST', $url, [
                    'json' => [
                        'timestamps' => $chunkTimestamps,
                        'selections' => $selections,
                    ],
                ]);
                $merge($this->parseResponse($response));
            } catch (\Throwable $e) {
                throw $this->framesRequestFailed($e);
            }
            $logChunk($i, count($chunks), count($chunkTimestamps), (int) round((microtime(true) - $start) * 1000));
        }
    }

    /**
     * All chunk requests through a Guzzle pool, at most $this->concurrency at a
     * time. The events server streams each reply while it computes it, so
     * overlapping the requests cuts the wait to roughly the slowest chunk
     * instead of the sum of all chunks.
     *
     * Replies can arrive in any order, but they are handed to $merge in chunk
     * order, so the result is the same as fetching one after another: a reply
     * that arrives early is held unparsed (Guzzle keeps bodies in php://temp,
     * which spills to disk past 2 MB) until the chunks before it are merged.
     * If any chunk fails, the lowest-index failure is reported and thrown, as
     * the sequential path would.
     */
    private function fetchChunksInParallel(string $url, array $chunks, array $selections, callable $merge, callable $logChunk, string $logLabel): void
    {
        $this->sentry->setContext('EventsApi', [
            'endpoint' => $url,
            'timestamp_count' => array_sum(array_map('count', $chunks)),
            'chunk_count' => count($chunks),
            'concurrency' => $this->concurrency,
            'selection_count' => count($selections),
        ]);

        $held = [];        // replies waiting for an earlier chunk, by chunk index
        $next = 0;         // the next chunk to merge
        $errors = [];      // failures, by chunk index
        $times = [];       // transfer time per chunk, for the log
        $requests = function () use ($url, $chunks, $selections, &$times) {
            foreach ($chunks as $i => $chunkTimestamps) {
                // A closure, not an arrow function: an arrow function captures
                // $times by value, and on_stats would fill a copy (every chunk
                // logged as 0 ms).
                yield $i => function () use ($url, $chunkTimestamps, $selections, &$times, $i) {
                    return $this->client->requestAsync('POST', $url, [
                        'json' => [
                            'timestamps' => $chunkTimestamps,
                            'selections' => $selections,
                        ],
                        'on_stats' => function (TransferStats $stats) use (&$times, $i) {
                            $times[$i] = $stats->getTransferTime();
                        },
                    ]);
                };
            }
        };
        $mergeReady = function () use (&$held, &$next, &$errors, $merge) {
            while (!$errors && isset($held[$next])) {
                try {
                    $merge($this->parseResponse($held[$next]));
                } catch (\Throwable $e) {
                    $errors[$next] = $e;
                }
                unset($held[$next]);
                $next++;
            }
        };

        $start = microtime(true);
        $pool = new Pool($this->client, $requests(), [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($response, $i) use (&$held, $mergeReady) {
                $held[$i] = $response;
                $mergeReady();
            },
            'rejected' => function ($reason, $i) use (&$errors) {
                $errors[$i] = $reason instanceof \Throwable ? $reason : new \RuntimeException((string) $reason);
            },
        ]);
        $pool->promise()->wait();

        if ($errors) {
            ksort($errors);
            throw $this->framesRequestFailed(reset($errors));
        }
        foreach ($chunks as $i => $chunkTimestamps) {
            $logChunk($i, count($chunks), count($chunkTimestamps), (int) round(1000 * ($times[$i] ?? 0)));
        }
        if ($logLabel !== '') {
            error_log(sprintf(
                "[%s] EventsApi frames_with_selections %d chunks, %d at a time, took %dms",
                $logLabel, count($chunks), $this->concurrency, (int) round((microtime(true) - $start) * 1000)
            ));
        }
    }

    /** Report a failed frames_with_selections request to Sentry and wrap it. */
    private function framesRequestFailed(\Throwable $e): EventsApiException
    {
        $this->sentry->setContext('EventsApi', [
            'error' => $e->getMessage(),
        ]);
        $exception = new EventsApiException("Failed to fetch frames_with_selections: " . $e->getMessage(), 0, $e);
        $this->sentry->capture($exception);
        return $exception;
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
