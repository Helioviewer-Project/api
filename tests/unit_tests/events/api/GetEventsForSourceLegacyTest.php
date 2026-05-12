<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiException;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class GetEventsForSourceLegacyTest extends TestCase
{
    private $mockClient;
    private $mockSentry;
    private $eventsApi;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
        $this->eventsApi = new EventsApi($this->mockClient, $this->mockSentry);
    }

    public function testItShouldReturnEventsOnSuccess(): void
    {
        $responseData = [
            ['id' => 1, 'type' => 'CME'],
            ['id' => 2, 'type' => 'Flare']
        ];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/helioviewer/events/CCMC/observation/'))
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $result = $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );

        $this->assertEquals($responseData, $result);
    }

    public function testItShouldSetEndpointContext(): void
    {
        $this->mockClient->method('request')
            ->willReturn(new Response(200, [], json_encode([])));

        $this->mockSentry->expects($this->exactly(2))
            ->method('setContext')
            ->withConsecutive(
                ['EventsApi', $this->anything()],
                ['EventsApi', $this->callback(function ($params) {
                    return array_key_exists('endpoint', $params)
                        && str_contains($params['endpoint'], '/helioviewer/events/CCMC/observation/');
                })]
            );

        $eventsApi = new EventsApi($this->mockClient, $this->mockSentry);
        $eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }

    public function testItShouldUrlEncodeObservationTime(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('2024-01-15+12%3A30%3A45'))
            ->willReturn(new Response(200, [], json_encode([])));

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:30:45'),
            'CCMC'
        );
    }

    public function testItShouldThrowAndCaptureOnError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('connection failed'));

        $this->mockSentry->expects($this->atLeastOnce())->method('setContext');
        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch events for source: connection failed');

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }

    public function testItShouldThrowOnInvalidJson(): void
    {
        $this->mockClient->method('request')
            ->willReturn(new Response(200, [], 'invalid json {'));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to decode JSON response');

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }

    public function testItShouldThrowWhenResponseIsNotArray(): void
    {
        $this->mockClient->method('request')
            ->willReturn(new Response(200, [], '"just a string"'));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Unexpected response format: expected array, got string');

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }
}
