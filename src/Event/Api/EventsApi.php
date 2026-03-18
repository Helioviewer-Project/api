<?php

namespace Helioviewer\Api\Event\Api;

use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

class EventsApi implements EventsApiInterface {

    private ClientInterface $client;
    private SentryClientInterface $sentry;

    /**
     * EventsApi constructor.
     *
     * @param ClientInterface|null $client Optional Guzzle client; if not provided, a new Client is created.
     * @param SentryClientInterface|null $sentry Optional Sentry client; if not provided, uses the global Sentry client.
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
        // Build the API URL: /api/v1/events/{source}/observation/{datetime}
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

    /**
     * Parse the HTTP response and decode JSON
     *
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return array
     * @throws EventsApiException if JSON decoding fails or response format is unexpected
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
