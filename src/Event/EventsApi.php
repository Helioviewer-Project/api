<?php

namespace Helioviewer\Api\Event;

use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Helioviewer\Api\Sentry\Sentry;

class EventsApi {

    private ClientInterface $client;

    /**
     * EventsApi constructor.
     *
     * @param ClientInterface|null $client Optional Guzzle client; if not provided, a new Client is created.
     */
    public function __construct(ClientInterface $client = null)
    {
        $timeout = defined('HV_EVENTS_API_TIMEOUT') ? HV_EVENTS_API_TIMEOUT : 10;
        $this->client = $client ?? new Client([
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
                'User-Agent' => 'Helioviewer-API/2.0'
            ]
        ]);
    }
    
    /**
     * Get events for a specific source
     *
     * @param DateTimeInterface $observationTime The observation time
     * @param string $source The data source (e.g. "CCMC")
     * @return array Array of event data
     * @throws EventsApiException on API errors or unexpected responses
     */
    public function getEventsForSourceLegacy(DateTimeInterface $observationTime, string $source): array 
    {
        // Build the API URL: /api/v1/events/{source}/observation/{datetime}
        $formattedTime = $observationTime->format('Y-m-d H:i:s');
        $encodedTime = urlencode($formattedTime);

        $baseUrl = defined('HV_EVENTS_API_URL') ? HV_EVENTS_API_URL : 'https://events.helioviewer.org';
        $url = $baseUrl . "/api/v1/events/{$source}/observation/{$encodedTime}";
        
        Sentry::setContext('EventsApi', [
            'url' => $url,
            'source' => $source,
            'observation_time' => $observationTime->format('Y-m-d\TH:i:s\Z')
        ]);

        $response = $this->client->request('GET', $url);
        
        return $this->parseResponse($response);
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
            Sentry::setContext('EventsApi', [
                'raw_response' => $body,
                'json_error' => json_last_error_msg(),
                'response_status' => $response->getStatusCode()
            ]);
            
            throw new EventsApiException("Failed to decode JSON response: " . json_last_error_msg());
        }

        if (!is_array($data)) {
            Sentry::setContext('EventsApi', [
                'unexpected_response_type' => gettype($data),
                'raw_response' => $body,
                'response_status' => $response->getStatusCode()
            ]);
            
            throw new EventsApiException("Unexpected response format: expected array, got " . gettype($data));
        }

        return $data;
    }
}
