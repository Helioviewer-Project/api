<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Helioviewer\Api\Event\EventsApi;
use Helioviewer\Api\Event\EventsApiException;

final class EventsApiTest extends TestCase
{
    private $mockClient;
    private $eventsApi;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->eventsApi = new EventsApi($this->mockClient);
    }

    public function testItShouldGetEventsSuccessfully(): void
    {
        $responseData = [
            ['id' => 1, 'type' => 'event'.rand()],
            ['id' => 2, 'type' => 'event'.rand()]
        ];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('GET', $this->stringContains('/api/v1/events/CCMC/observation/'))
            ->willReturn(new Response(200, [], json_encode($responseData)));

        $result = $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );

        $this->assertEquals($responseData, $result);
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

    public function testItShouldThrowExceptionOnInvalidJson(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], 'invalid json {'));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to decode JSON response');

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }

    public function testItShouldThrowExceptionWhenResponseIsNotArray(): void
    {
        $this->mockClient->expects($this->once())
            ->method('request')
            ->willReturn(new Response(200, [], '"just a string"'));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Unexpected response format: expected array, got string');

        $this->eventsApi->getEventsForSourceLegacy(
            new DateTimeImmutable('2024-01-15 12:00:00'),
            'CCMC'
        );
    }
}
