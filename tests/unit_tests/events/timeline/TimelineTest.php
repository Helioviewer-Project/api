<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\Timeline;
use Helioviewer\Api\Event\Timeline\CoverageInterface;
use Helioviewer\Api\Event\Timeline\TimeRange;
use Helioviewer\Api\Event\Api\EventsApiInterface;

final class TimelineTest extends TestCase
{
    private $mockEventsApi;
    private $mockStrategy;

    protected function setUp(): void
    {
        $this->mockEventsApi = $this->createMock(EventsApiInterface::class);
        $this->mockStrategy = $this->createMock(CoverageInterface::class);
    }

    public function testItShouldPassCorrectParamsToStrategy(): void
    {
        $this->mockStrategy->expects($this->once())
            ->method('execute')
            ->with(
                $this->identicalTo($this->mockEventsApi),
                // Original: 1000 to 3000, distance = 2000
                // Extended: -1000 to 5000
                $this->callback(function (TimeRange $range) {
                    return $range->start() === -1000 && $range->end() === 5000;
                }),
                // [AR,all,1],[C3,all,1] → paths
                $this->equalTo(['HEK>>Active Region', 'CCMC>>DONKI']),
                // Range 2000ms → minute resolution
                $this->equalTo('m')
            )
            ->willReturn([]);

        $timeline = new Timeline(
            '[AR,all,1],[C3,all,1]', 1000, 3000, 2000,
            $this->mockEventsApi,
            $this->mockStrategy
        );

        $timeline->execute();
    }

    public function testItShouldReturnJsonEncodedStrategyOutput(): void
    {
        $strategyOutput = [
            ['data' => [[1000, 5]], 'event_type' => 'AR', 'res' => 'h', 'showInLegend' => true],
            ['data' => [[1000, 3]], 'event_type' => 'FL', 'res' => 'h', 'showInLegend' => true],
        ];

        $this->mockStrategy->method('execute')->willReturn($strategyOutput);

        $timeline = new Timeline(
            '[AR,all,1]', 1000, 2000, 1500,
            $this->mockEventsApi,
            $this->mockStrategy
        );

        $this->assertEquals(json_encode($strategyOutput), $timeline->execute());
    }
}
