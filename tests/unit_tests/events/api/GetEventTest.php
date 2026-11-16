<?php declare(strict_types=1);

/**
 * EventsApi::getEvent: one event by id from the v1 endpoint.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiException;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class GetEventTest extends TestCase
{
    private const UUID = '01a0f8f2-502e-732d-a2cc-e46d43dc1292';

    private $mockClient;
    private $mockSentry;
    private $eventsApi;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
        $this->eventsApi = new EventsApi($this->mockClient, $this->mockSentry);
    }

    public function testItShouldReturnTheEventFromTheV1EndpointOnSuccess(): void
    {
        $event = ['id' => self::UUID, 'type' => 'AR', 'label' => 'AR 13700', 'source' => ['frm_name' => 'SPoCA']];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('GET', '/api/v1/events/' . self::UUID)
            ->willReturn(new Response(200, [], json_encode($event)));

        $this->assertSame($event, $this->eventsApi->getEvent(self::UUID));
    }

    public function testItShouldReturnNullWhenTheEventsApiAnswers404(): void
    {
        $request = new Request('GET', '/api/v1/events/' . self::UUID);
        $this->mockClient->method('request')
            ->willThrowException(new ClientException('Not Found', $request, new Response(404, [], '{"detail":"Not Found"}')));

        $this->mockSentry->expects($this->never())->method('capture');

        $this->assertNull($this->eventsApi->getEvent(self::UUID));
    }

    public function testItShouldThrowAndCaptureOnOtherClientErrors(): void
    {
        $request = new Request('GET', '/api/v1/events/' . self::UUID);
        $this->mockClient->method('request')
            ->willThrowException(new ClientException('Forbidden', $request, new Response(403)));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch event: Forbidden');

        $this->eventsApi->getEvent(self::UUID);
    }

    public function testItShouldThrowAndCaptureOnServerErrors(): void
    {
        $request = new Request('GET', '/api/v1/events/' . self::UUID);
        $this->mockClient->method('request')
            ->willThrowException(new ServerException('Bad Gateway', $request, new Response(502)));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch event: Bad Gateway');

        $this->eventsApi->getEvent(self::UUID);
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

        $this->eventsApi->getEvent(self::UUID);
    }

    public function testItShouldSetEndpointContext(): void
    {
        $this->mockClient->method('request')
            ->willReturn(new Response(200, [], json_encode(['id' => self::UUID])));

        $this->mockSentry->expects($this->exactly(2))
            ->method('setContext')
            ->withConsecutive(
                ['EventsApi', $this->anything()],
                ['EventsApi', ['endpoint' => '/api/v1/events/' . self::UUID]]
            );

        $eventsApi = new EventsApi($this->mockClient, $this->mockSentry);
        $eventsApi->getEvent(self::UUID);
    }
}
