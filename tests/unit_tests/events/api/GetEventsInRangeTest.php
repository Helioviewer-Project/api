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

final class GetEventsInRangeTest extends TestCase
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

    public function testItShouldThrowAndCaptureOnError(): void
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
}
