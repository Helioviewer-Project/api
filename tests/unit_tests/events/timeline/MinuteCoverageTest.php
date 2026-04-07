<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\MinuteCoverage;
use Helioviewer\Api\Event\Timeline\SwimLaner;
use Helioviewer\Api\Event\Timeline\TimeRange;
use Helioviewer\Api\Event\Api\EventsApiInterface;

final class MinuteCoverageTest extends TestCase
{
    private $mockEventsApi;
    private $mockSwimLaner;
    private MinuteCoverage $coverage;

    protected function setUp(): void
    {
        $this->mockEventsApi = $this->createMock(EventsApiInterface::class);
        $this->mockSwimLaner = $this->createMock(SwimLaner::class);
        $this->mockSwimLaner->method('assign')->willReturnArgument(0);
        $this->coverage = new MinuteCoverage($this->mockSwimLaner);
    }

    public function testItShouldReturnEmptyForEmptyPaths(): void
    {
        $this->mockEventsApi->expects($this->never())->method('getEventsInRange');
        $this->mockSwimLaner->expects($this->never())->method('assign');

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1000, 2000, 1500),
            [],
            'm'
        );

        $this->assertEquals([], $result);
    }

    public function testItShouldCallEventsApiWithExtendedRange(): void
    {
        // visible: 5000-10000, distance=5000, extended: 0-15000
        $range = new TimeRange(5000000, 10000000, 7000000);

        $this->mockEventsApi->expects($this->once())
            ->method('getEventsInRange')
            ->with(
                $range->extendedStartSec(),
                $range->extendedEndSec(),
                ['HEK>>Active Region']
            )
            ->willReturn(['events' => []]);

        $this->coverage->execute($this->mockEventsApi, $range, ['HEK>>Active Region'], 'm');
    }

    public function testItShouldGroupEventsByTypeInSwimLaner(): void
    {
        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 12:00:00', 'event_endtime' => '2024-01-15 13:00:00'],
                ['event_type' => 'FL', 'event_starttime' => '2024-01-15 12:30:00', 'event_endtime' => '2024-01-15 13:30:00'],
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 14:00:00', 'event_endtime' => '2024-01-15 15:00:00'],
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 16:00:00', 'event_endtime' => '2024-01-15 17:00:00'],
            ]
        ]);

        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->with($this->callback(function ($series) {
                return isset($series['AR'])
                    && isset($series['FL'])
                    && count($series['AR']['data']) === 3
                    && count($series['FL']['data']) === 1;
            }))
            ->willReturnArgument(0);

        $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1705312800000, 1705330800000, 1705320000000),
            ['HEK>>Active Region'],
            'm'
        );
    }

    public function testItShouldUseUnkForMissingEventType(): void
    {
        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                ['event_starttime' => '2024-01-15 12:00:00', 'event_endtime' => '2024-01-15 13:00:00'],
            ]
        ]);

        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->with($this->callback(function ($series) {
                return isset($series['UNK']) && count($series['UNK']['data']) === 1;
            }))
            ->willReturnArgument(0);

        $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1705312800000, 1705330800000, 1705320000000),
            ['HEK>>Active Region'],
            'm'
        );
    }

    public function testItShouldClampEventsToExtendedRange(): void
    {
        // visible: 12:00-14:00, distance=2h, extended: 10:00-16:00
        $visStart = strtotime('2024-01-15 12:00:00') * 1000;
        $visEnd = strtotime('2024-01-15 14:00:00') * 1000;
        $range = new TimeRange($visStart, $visEnd, $visStart);

        $extStart = $range->extendedStart();
        $extEnd = $range->extendedEnd();

        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                // a) extends beyond both sides → clamp both
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 09:00:00', 'event_endtime' => '2024-01-15 17:00:00'],
                // b) inside extended range → no clamp
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 11:00:00', 'event_endtime' => '2024-01-15 13:00:00'],
                // c) ends beyond extended → clamp x2 only
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 15:30:00', 'event_endtime' => '2024-01-15 17:30:00'],
            ]
        ]);

        $insideStart = strtotime('2024-01-15 11:00:00') * 1000;
        $insideEnd = strtotime('2024-01-15 13:00:00') * 1000;
        $cStart = strtotime('2024-01-15 15:30:00') * 1000;

        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->with($this->callback(function ($series) use ($extStart, $extEnd, $insideStart, $insideEnd, $cStart) {
                $data = $series['AR']['data'];
                // a) clamped both sides
                $a = $data[0]['x'] === $extStart && $data[0]['x2'] === $extEnd;
                // b) no clamp
                $b = $data[1]['x'] === $insideStart && $data[1]['x2'] === $insideEnd;
                // c) x stays, x2 clamped
                $c = $data[2]['x'] === $cStart && $data[2]['x2'] === $extEnd;
                return $a && $b && $c;
            }))
            ->willReturnArgument(0);

        $this->coverage->execute($this->mockEventsApi, $range, ['HEK>>Active Region'], 'm');
    }

    public function testItShouldShowInLegendWhenEventOverlapsVisibleRange(): void
    {
        // visible: 12:00-14:00
        $visStart = strtotime('2024-01-15 12:00:00') * 1000;
        $visEnd = strtotime('2024-01-15 14:00:00') * 1000;
        $range = new TimeRange($visStart, $visEnd, $visStart);

        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                // overlaps visible
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 13:00:00', 'event_endtime' => '2024-01-15 15:00:00'],
                // inside visible
                ['event_type' => 'FL', 'event_starttime' => '2024-01-15 12:30:00', 'event_endtime' => '2024-01-15 13:30:00'],
                // barely overlaps end of visible
                ['event_type' => 'CH', 'event_starttime' => '2024-01-15 13:59:00', 'event_endtime' => '2024-01-15 14:01:00'],
            ]
        ]);

        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->with($this->callback(function ($series) {
                return $series['AR']['showInLegend'] === true
                    && $series['FL']['showInLegend'] === true
                    && $series['CH']['showInLegend'] === true;
            }))
            ->willReturnArgument(0);

        $this->coverage->execute($this->mockEventsApi, $range, ['HEK>>Active Region'], 'm');
    }

    public function testItShouldNotShowInLegendWhenEventOnlyInExtendedRange(): void
    {
        // visible: 12:00-14:00, extended: 10:00-16:00
        $visStart = strtotime('2024-01-15 12:00:00') * 1000;
        $visEnd = strtotime('2024-01-15 14:00:00') * 1000;
        $range = new TimeRange($visStart, $visEnd, $visStart);

        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                // extended only, before visible
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 10:30:00', 'event_endtime' => '2024-01-15 11:30:00'],
                // extended only, after visible
                ['event_type' => 'FL', 'event_starttime' => '2024-01-15 14:30:00', 'event_endtime' => '2024-01-15 15:30:00'],
                // in visible
                ['event_type' => 'CH', 'event_starttime' => '2024-01-15 13:00:00', 'event_endtime' => '2024-01-15 13:30:00'],
            ]
        ]);

        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->with($this->callback(function ($series) {
                return $series['AR']['showInLegend'] === false
                    && $series['FL']['showInLegend'] === false
                    && $series['CH']['showInLegend'] === true;
            }))
            ->willReturnArgument(0);

        $this->coverage->execute($this->mockEventsApi, $range, ['HEK>>Active Region'], 'm');
    }

    public function testItShouldReturnIndexedArray(): void
    {
        $this->mockEventsApi->method('getEventsInRange')->willReturn([
            'events' => [
                ['event_type' => 'AR', 'event_starttime' => '2024-01-15 12:00:00', 'event_endtime' => '2024-01-15 13:00:00'],
                ['event_type' => 'FL', 'event_starttime' => '2024-01-15 12:00:00', 'event_endtime' => '2024-01-15 13:00:00'],
            ]
        ]);

        // SwimLaner returns keyed by type
        $this->mockSwimLaner->expects($this->once())
            ->method('assign')
            ->willReturn([
                'AR' => ['data' => [['x' => 1, 'x2' => 2, 'y' => 1]], 'event_type' => 'AR', 'res' => 'm', 'showInLegend' => true],
                'FL' => ['data' => [['x' => 1, 'x2' => 2, 'y' => 2]], 'event_type' => 'FL', 'res' => 'm', 'showInLegend' => true],
            ]);

        $result = $this->coverage->execute(
            $this->mockEventsApi,
            new TimeRange(1705312800000, 1705330800000, 1705320000000),
            ['HEK>>Active Region'],
            'm'
        );

        // Should be indexed 0, 1 — not keyed by 'AR', 'FL'
        $this->assertArrayHasKey(0, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayNotHasKey('AR', $result);
        $this->assertArrayNotHasKey('FL', $result);
    }
}
