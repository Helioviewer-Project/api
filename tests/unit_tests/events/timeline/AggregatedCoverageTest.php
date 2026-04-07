<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\AggregatedCoverage;
use Helioviewer\Api\Event\Timeline\TimeRange;
use Helioviewer\Api\Event\Api\EventsApiInterface;

final class AggregatedCoverageTest extends TestCase
{
    private $mockEventsApi;
    private AggregatedCoverage $coverage;

    protected function setUp(): void
    {
        $this->mockEventsApi = $this->createMock(EventsApiInterface::class);
        $this->coverage = new AggregatedCoverage();
    }

    public function testItShouldReturnEmptyForEmptyPaths(): void
    {
        $this->mockEventsApi->expects($this->never())->method('getDistributions');

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1000, 2000, 1500),
            [],
            'h'
        );

        $this->assertEquals([], $result);
    }

    public function testItShouldCallEventsApiWithExtendedRangeAndResolution(): void
    {
        $range = new TimeRange(5000000, 10000000, 7000000);
        $paths = ['HEK>>Active Region', 'CCMC>>DONKI'];

        $this->mockEventsApi->expects($this->once())
            ->method('getDistributions')
            ->with(
                'h',
                $range->extendedStartSec(),
                $range->extendedEndSec(),
                $paths
            )
            ->willReturn(['event_types' => [], 'buckets' => []]);

        $this->coverage->execute($this->mockEventsApi, $range, $paths, 'h');
    }

    public function testItShouldBuildSeriesFromBuckets(): void
    {
        $this->mockEventsApi->method('getDistributions')->willReturn([
            'event_types' => ['AR', 'FL', 'CH'],
            'buckets' => [
                ['start' => 1000, 'counts' => ['AR' => 5, 'FL' => 3, 'CH' => 0]],
                ['start' => 2000, 'counts' => ['AR' => 2, 'FL' => 7, 'CH' => 1]],
                ['start' => 3000, 'counts' => ['AR' => 0, 'FL' => 0, 'CH' => 12]],
                ['start' => 4000, 'counts' => ['AR' => 1, 'FL' => 1, 'CH' => 1]],
            ]
        ]);

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1000000, 5000000, 3000000),
            ['HEK>>Active Region'],
            'h'
        );

        $this->assertCount(3, $result);

        // Find each series by event_type
        $byType = [];
        foreach ($result as $series) {
            $byType[$series['event_type']] = $series;
        }

        // AR
        $this->assertEquals([
            [1000000, 5], [2000000, 2], [3000000, 0], [4000000, 1]
        ], $byType['AR']['data']);
        $this->assertEquals('h', $byType['AR']['res']);
        $this->assertTrue($byType['AR']['showInLegend']);

        // FL
        $this->assertEquals([
            [1000000, 3], [2000000, 7], [3000000, 0], [4000000, 1]
        ], $byType['FL']['data']);

        // CH
        $this->assertEquals([
            [1000000, 0], [2000000, 1], [3000000, 12], [4000000, 1]
        ], $byType['CH']['data']);
    }

    public function testItShouldFillMissingEventTypesWithZero(): void
    {
        $this->mockEventsApi->method('getDistributions')->willReturn([
            'event_types' => ['AR', 'FL'],
            'buckets' => [
                ['start' => 1000, 'counts' => ['AR' => 5]],
                ['start' => 2000, 'counts' => ['FL' => 3]],
                ['start' => 3000, 'counts' => []],
            ]
        ]);

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1000000, 4000000, 2000000),
            ['HEK>>Active Region'],
            'D'
        );

        $byType = [];
        foreach ($result as $series) {
            $byType[$series['event_type']] = $series;
        }

        // AR: present in bucket 1, missing in 2 and 3
        $this->assertEquals([
            [1000000, 5], [2000000, 0], [3000000, 0]
        ], $byType['AR']['data']);

        // FL: missing in bucket 1 and 3, present in 2
        $this->assertEquals([
            [1000000, 0], [2000000, 3], [3000000, 0]
        ], $byType['FL']['data']);
    }

    public function testItShouldReturnSortedByEventType(): void
    {
        $this->mockEventsApi->method('getDistributions')->willReturn([
            'event_types' => ['FL', 'AR', 'CH'],
            'buckets' => [
                ['start' => 1000, 'counts' => ['FL' => 1, 'AR' => 2, 'CH' => 3]],
            ]
        ]);

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1000000, 2000000, 1500000),
            ['HEK>>Active Region'],
            'W'
        );

        $this->assertEquals('AR', $result[0]['event_type']);
        $this->assertEquals('CH', $result[1]['event_type']);
        $this->assertEquals('FL', $result[2]['event_type']);
    }
}
