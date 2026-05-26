<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Psr7\Response;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiException;
use Helioviewer\Api\Event\Api\LegacyEventsInterface;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class GetEventsBatchTest extends TestCase
{
    private $mockClient;
    private $mockSentry;
    private $mockLegacyEvents;
    private $eventsApi;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
        $this->mockLegacyEvents = $this->createMock(LegacyEventsInterface::class);
        $this->eventsApi = new EventsApi($this->mockClient, $this->mockSentry, $this->mockLegacyEvents);
    }

    public function testItShouldReturnEmptyForEmptyTimestamps(): void
    {
        $this->mockClient->expects($this->never())->method('request');
        $this->mockLegacyEvents->expects($this->never())->method('convertAll');

        $result = $this->eventsApi->getEventsBatch([], ['HEK']);
        $this->assertEquals([], $result);
    }

    public function testItShouldThrowForInvalidSources(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('No valid sources given');

        $this->eventsApi->getEventsBatch(['2024-01-15 12:00:00'], ['FOO', 'BAR']);
    }

    public function testItShouldCallCorrectUrlWithJoinedSourcesAndTimestamps(): void
    {
        $batchResponse = ['event_types' => [], 'events' => [], 'observations' => []];

        $this->mockClient->expects($this->once())
            ->method('request')
            ->with('POST', '/helioviewer/events/HEK::CCMC/observations', $this->callback(function ($options) {
                return $options['json']['timestamps'] === ['2024-01-15 12:00:00', '2024-01-15 12:01:00'];
            }))
            ->willReturn(new Response(200, [], json_encode($batchResponse)));

        $this->mockLegacyEvents->method('convertAll')->willReturn([]);

        $this->eventsApi->getEventsBatch(
            ['2024-01-15 12:00:00', '2024-01-15 12:01:00'],
            ['HEK', 'CCMC']
        );
    }

    public function testItShouldPaginateTimestampsAt150(): void
    {
        $timestamps = [];
        for ($i = 0; $i < 200; $i++) {
            $timestamps[] = "2024-01-15 " . sprintf('%02d:%02d:00', intdiv($i, 60), $i % 60);
        }

        $batchResponse = ['event_types' => [], 'events' => [], 'observations' => []];

        $this->mockClient->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['POST', '/helioviewer/events/HEK/observations', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 150;
                })],
                ['POST', '/helioviewer/events/HEK/observations', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 50;
                })]
            )
            ->willReturn(new Response(200, [], json_encode($batchResponse)));

        $this->mockLegacyEvents->method('convertAll')->willReturn([]);

        $this->eventsApi->getEventsBatch($timestamps, ['HEK'], 150);
    }

    public function testItShouldThrowAndCaptureSentryOnHttpError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('connection refused'));

        $this->mockSentry->expects($this->atLeastOnce())
            ->method('setContext')
            ->with('EventsApi', $this->callback(function ($params) {
                return array_key_exists('error', $params) || array_key_exists('endpoint', $params) || array_key_exists('api_url', $params);
            }));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch batch events: connection refused');

        $this->eventsApi->getEventsBatch(['2024-01-15 12:00:00'], ['HEK']);
    }

    public function testItShouldMergeObservationsAcrossChunksAndPassToConverter(): void
    {
        $timestamps = [];
        for ($i = 0; $i < 160; $i++) {
            $timestamps[] = "2024-01-15 " . sprintf('%02d:%02d:00', intdiv($i, 60), $i % 60);
        }

        $chunk1Response = [
            'event_types' => [['pin' => 'AR', 'name' => 'Active Region', 'groups' => []]],
            'events' => ['evt1' => ['label' => 'AR 1']],
            'observations' => [
                '2024-01-15 00:00:00' => ['evt1' => ['hv_hpc_x' => 1.0, 'hv_hpc_y' => 2.0]],
                '2024-01-15 00:01:00' => ['evt1' => ['hv_hpc_x' => 1.1, 'hv_hpc_y' => 2.1]],
            ]
        ];

        $chunk2Response = [
            'event_types' => [['pin' => 'AR', 'name' => 'Active Region', 'groups' => []]],
            'events' => ['evt1' => ['label' => 'AR 1']],
            'observations' => [
                '2024-01-15 02:30:00' => ['evt1' => ['hv_hpc_x' => 3.0, 'hv_hpc_y' => 4.0]],
            ]
        ];

        $this->mockClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode($chunk1Response)),
                new Response(200, [], json_encode($chunk2Response))
            );

        // Verify convertAll receives merged observations from both chunks
        $this->mockLegacyEvents->expects($this->once())
            ->method('convertAll')
            ->with($this->callback(function ($merged) {
                $obs = $merged['observations'];
                return count($obs) === 3
                    && isset($obs['2024-01-15 00:00:00'])
                    && isset($obs['2024-01-15 00:01:00'])
                    && isset($obs['2024-01-15 02:30:00'])
                    && $obs['2024-01-15 02:30:00']['evt1']['hv_hpc_x'] == 3.0;
            }))
            ->willReturn([]);

        $this->eventsApi->getEventsBatch($timestamps, ['HEK'], 150);
    }
}
