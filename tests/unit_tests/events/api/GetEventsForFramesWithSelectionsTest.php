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

final class GetEventsForFramesWithSelectionsTest extends TestCase
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

    public function testItShouldThrowForEmptySelections(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('No selections given');

        $this->eventsApi->getEventsForFramesWithSelections(['2024-01-15 12:00:00'], []);
    }

    public function testItShouldThrowWhenSelectionsExceedUpstreamLimit(): void
    {
        $tooMany = array_fill(0, EventsApi::maxSelections() + 1, 'HEK>>Flare');

        $this->mockClient->expects($this->never())->method('request');

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Too many selections');

        $this->eventsApi->getEventsForFramesWithSelections(['2024-01-15 12:00:00'], $tooMany);
    }

    public function testItShouldReturnEmptyForEmptyTimestamps(): void
    {
        $this->mockClient->expects($this->never())->method('request');

        $result = $this->eventsApi->getEventsForFramesWithSelections([], ['HEK>>Flare']);
        $this->assertEquals([], $result);
    }

    public function testItShouldFallBackToConfiguredChunkSizeWhenCallerPassesLessThanOne(): void
    {
        // 60 timestamps with chunkSize=0 -> defined config or fallback 50 -> 2 chunks (50 + 10)
        $timestamps = [];
        for ($i = 0; $i < 60; $i++) {
            $timestamps[] = "2024-01-15 " . sprintf('%02d:%02d:00', intdiv($i, 60), $i % 60);
        }

        $emptyResponse = ['events' => [], 'timestamps' => []];

        $this->mockClient->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 50;
                })],
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 10;
                })]
            )
            ->willReturn(new Response(200, [], json_encode($emptyResponse)));

        $this->eventsApi->getEventsForFramesWithSelections($timestamps, ['HEK>>Flare'], 0);
    }

    public function testItShouldCapChunkSizeAtUpstreamLimit(): void
    {
        // Asking for chunkSize 999 with 200 timestamps -> capped to 150 -> 2 chunks (150 + 50)
        $timestamps = [];
        for ($i = 0; $i < 200; $i++) {
            $timestamps[] = "2024-01-15 " . sprintf('%02d:%02d:00', intdiv($i, 60), $i % 60);
        }

        $emptyResponse = ['events' => [], 'timestamps' => []];

        $this->mockClient->expects($this->exactly(2))
            ->method('request')
            ->withConsecutive(
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 150;
                })],
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 50;
                })]
            )
            ->willReturn(new Response(200, [], json_encode($emptyResponse)));

        $this->eventsApi->getEventsForFramesWithSelections($timestamps, ['HEK>>Flare'], 999);
    }

    public function testItShouldPaginateTimestampsAtTheGivenChunkSize(): void
    {
        // 7 timestamps, chunkSize=3 -> 3 chunks of (3, 3, 1)
        $timestamps = [
            '2024-01-15 12:00:00', '2024-01-15 12:01:00', '2024-01-15 12:02:00',
            '2024-01-15 12:03:00', '2024-01-15 12:04:00', '2024-01-15 12:05:00',
            '2024-01-15 12:06:00',
        ];

        $emptyResponse = ['events' => [], 'timestamps' => []];

        $this->mockClient->expects($this->exactly(3))
            ->method('request')
            ->withConsecutive(
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 3
                        && $options['json']['selections'] === ['HEK>>Flare', 'CCMC>>DONKI>>CME'];
                })],
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 3
                        && $options['json']['selections'] === ['HEK>>Flare', 'CCMC>>DONKI>>CME'];
                })],
                ['POST', '/helioviewer/events/frames_with_selections', $this->callback(function ($options) {
                    return count($options['json']['timestamps']) === 1
                        && $options['json']['selections'] === ['HEK>>Flare', 'CCMC>>DONKI>>CME'];
                })]
            )
            ->willReturn(new Response(200, [], json_encode($emptyResponse)));

        $this->eventsApi->getEventsForFramesWithSelections(
            $timestamps,
            ['HEK>>Flare', 'CCMC>>DONKI>>CME'],
            3
        );
    }

    public function testItShouldThrowAndCaptureSentryOnHttpError(): void
    {
        $this->mockClient->method('request')
            ->willThrowException(new \RuntimeException('connection refused'));

        $this->mockSentry->expects($this->atLeastOnce())
            ->method('setContext')
            ->with('EventsApi', $this->callback(function ($params) {
                return array_key_exists('error', $params)
                    || array_key_exists('endpoint', $params)
                    || array_key_exists('api_url', $params);
            }));

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch frames_with_selections: connection refused');

        $this->eventsApi->getEventsForFramesWithSelections(
            ['2024-01-15 12:00:00'],
            ['HEK>>Flare']
        );
    }

    public function testItShouldMergeEventsAndTimestampsAcrossChunks(): void
    {
        // 4 timestamps, chunkSize=2 -> 2 chunks
        $timestamps = [
            '2024-01-15 12:00:00', '2024-01-15 12:01:00',
            '2024-01-15 12:02:00', '2024-01-15 12:03:00',
        ];

        $chunk1 = [
            'events' => [
                'evt-FOO' => ['path' => 'HEK>>Flare', 'label' => 'FOO event', 'hv_hpc_x' => 1.0, 'hv_hpc_y' => 2.0],
                'evt-BAR' => ['path' => 'HEK>>Flare', 'label' => 'BAR event', 'hv_hpc_x' => 3.0, 'hv_hpc_y' => 4.0],
            ],
            'timestamps' => [
                '2024-01-15 12:00:00' => ['evt-FOO' => ['hv_hpc_x' => 1.1, 'hv_hpc_y' => 2.1]],
                '2024-01-15 12:01:00' => ['evt-FOO' => ['hv_hpc_x' => 1.2, 'hv_hpc_y' => 2.2]],
            ],
        ];

        $chunk2 = [
            'events' => [
                'evt-BAR' => ['path' => 'HEK>>Flare', 'label' => 'BAR event', 'hv_hpc_x' => 3.0, 'hv_hpc_y' => 4.0],
                'evt-BAZ' => ['path' => 'HEK>>Flare', 'label' => 'BAZ event', 'hv_hpc_x' => 5.0, 'hv_hpc_y' => 6.0],
            ],
            'timestamps' => [
                '2024-01-15 12:02:00' => ['evt-BAR' => ['hv_hpc_x' => 3.5, 'hv_hpc_y' => 4.5]],
                '2024-01-15 12:03:00' => ['evt-BAZ' => ['hv_hpc_x' => 5.5, 'hv_hpc_y' => 6.5]],
            ],
        ];

        $this->mockClient->expects($this->exactly(2))
            ->method('request')
            ->willReturnOnConsecutiveCalls(
                new Response(200, [], json_encode($chunk1)),
                new Response(200, [], json_encode($chunk2))
            );

        $merged = $this->eventsApi->getEventsForFramesWithSelections(
            $timestamps,
            ['HEK>>Flare'],
            2
        );

        // Union of events across both chunks
        $this->assertCount(3, $merged['events']);
        $this->assertArrayHasKey('evt-FOO', $merged['events']);
        $this->assertArrayHasKey('evt-BAR', $merged['events']);
        $this->assertArrayHasKey('evt-BAZ', $merged['events']);

        // Union of timestamps across both chunks (4 unique)
        $this->assertCount(4, $merged['timestamps']);
        $this->assertEquals(1.1, $merged['timestamps']['2024-01-15 12:00:00']['evt-FOO']['hv_hpc_x']);
        $this->assertEquals(3.5, $merged['timestamps']['2024-01-15 12:02:00']['evt-BAR']['hv_hpc_x']);
        $this->assertEquals(5.5, $merged['timestamps']['2024-01-15 12:03:00']['evt-BAZ']['hv_hpc_x']);
    }
}
