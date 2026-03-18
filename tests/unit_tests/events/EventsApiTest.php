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

final class EventsApiTest extends TestCase
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

    public function testConstructorSetsDefaultSentryContext(): void
    {
        $this->mockSentry->expects($this->once())
            ->method('setContext')
            ->with('EventsApi', $this->callback(function ($params) {
                return array_key_exists('api_url', $params)
                    && array_key_exists('timeout', $params)
                    && array_key_exists('connect_timeout', $params);
            }));

        new EventsApi($this->mockClient, $this->mockSentry);
    }

    public function testGetEventsForSourceLegacySuccess(): void
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

    public function testGetEventsForSourceLegacySetsEndpointContext(): void
    {
        $this->mockClient->method('request')
            ->willReturn(new Response(200, [], json_encode([])));

        // First call is from constructor, second from the method
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

    public function testGetEventsForSourceLegacyUrlEncodesObservationTime(): void
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

    public function testGetEventsForSourceLegacyThrowsAndCapturesOnError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('connection failed'));

        // Constructor setContext + method setContext + error setContext = 3, then capture
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

    public function testGetEventsForSourceLegacyThrowsOnInvalidJson(): void
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

    public function testGetEventsForSourceLegacyThrowsWhenResponseIsNotArray(): void
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

    public function testGetEventsInRangeSuccess(): void
    {
        $responseData = [['id' => 1, 'type' => 'CME']];
        $paths = ['CCMC>>DONKI>>CME', 'HEK>>Active Region'];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('POST', '/helioviewer/events/from/1000/to/2000', [
                'json' => ['paths' => $paths]
            ])
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $result = $this->eventsApi->getEventsInRange(1000, 2000, $paths);

        $this->assertEquals($responseData, $result);
    }

    public function testGetEventsInRangeThrowsAndCapturesOnError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('timeout'));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch events: timeout');

        $this->eventsApi->getEventsInRange(1000, 2000, ['CCMC>>DONKI>>CME']);
    }

    public function testGetDistributionsSuccess(): void
    {
        $responseData = [['bucket' => '2024-01-15', 'count' => 5]];
        $paths = ['CCMC>>DONKI>>CME'];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('POST', '/helioviewer/distributions/size/h/from/1000/to/2000', [
                'json' => ['paths' => $paths]
            ])
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $result = $this->eventsApi->getDistributions('h', 1000, 2000, $paths);

        $this->assertEquals($responseData, $result);
    }

    public function testGetDistributionsThrowsAndCapturesOnError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('server error'));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch distributions: server error');

        $this->eventsApi->getDistributions('h', 1000, 2000, ['CCMC>>DONKI>>CME']);
    }
}
