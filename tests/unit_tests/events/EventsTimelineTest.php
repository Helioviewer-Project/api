<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventsTimeline;
use Helioviewer\Api\Event\Api\EventsApiInterface;

final class EventsTimelineTest extends TestCase
{
    private $mockEventsApi;

    protected function setUp(): void
    {
        $this->mockEventsApi = $this->createMock(EventsApiInterface::class);
    }

    public static function invalidTimestampProvider(): array
    {
        return [
            'null_start' => [null, 2000, 1500],
            'null_end' => [1000, null, 1500],
            'null_current' => [1000, 2000, null],
            'zero_start' => [0, 2000, 1500],
            'zero_end' => [1000, 0, 1500],
            'zero_current' => [1000, 2000, 0],
            'negative_start' => [-1, 2000, 1500],
            'string_start' => ['abc', 2000, 1500],
            'string_end' => [1000, 'abc', 1500],
            'string_current' => [1000, 2000, 'abc'],
        ];
    }

    /**
     * @dataProvider invalidTimestampProvider
     */
    public function testItShouldThrowForInvalidTimestamps($start, $end, $current): void
    {
        $this->expectException(InvalidArgumentException::class);
        new EventsTimeline('[AR,all,1]', $start, $end, $current, $this->mockEventsApi);
    }

    public function testItShouldThrowWhenStartIsGreaterThanEnd(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('startTimestamp must be less than or equal to endTimestamp');
        new EventsTimeline('[AR,all,1]', 2000, 1000, 1500, $this->mockEventsApi);
    }

    public function testItShouldNotThrowWhenStartEqualsEnd(): void
    {
        $this->expectNotToPerformAssertions();
        new EventsTimeline('[AR,all,1]', 1000, 1000, 1000, $this->mockEventsApi);
    }

    public function testItShouldNotThrowForValidTimestamps(): void
    {
        $this->expectNotToPerformAssertions();
        new EventsTimeline('[AR,all,1]', 1000, 2000, 1500, $this->mockEventsApi);
    }

    public function testItShouldStoreTimestamps(): void
    {
        $timeline = new EventsTimeline('[AR,all,1]', 1000, 2000, 1500, $this->mockEventsApi);
        $this->assertEquals(1000, $timeline->getStartMs());
        $this->assertEquals(2000, $timeline->getEndMs());
        $this->assertEquals(1500, $timeline->getCurrentMs());
    }

    public function testItShouldParseEventSelections(): void
    {
        $timeline = new EventsTimeline('[AR,all,1],[C3,all,1]', 1000, 2000, 1500, $this->mockEventsApi);
        $selections = $timeline->getEventSelections();
        $this->assertCount(2, $selections);
        $this->assertEquals('HEK>>Active Region', $selections[0]);
        $this->assertEquals('CCMC>>DONKI', $selections[1]);
    }

    public function testItShouldParseEmptyEventSelections(): void
    {
        $timeline = new EventsTimeline('', 1000, 2000, 1500, $this->mockEventsApi);
        $this->assertCount(0, $timeline->getEventSelections());
    }

    public static function resolutionProvider(): array
    {
        return [
            'under_1_day_minute' => [
                1000,
                1000 + 86400000 - 1,
                'm',
            ],
            'exactly_1_day_30m' => [
                1000,
                1000 + 86400000,
                '30m',
            ],
            'under_2_days_30m' => [
                1000,
                1000 + 172800000 - 1,
                '30m',
            ],
            'under_10_days_hourly' => [
                1000,
                1000 + 864000000 - 1,
                'h',
            ],
            'under_6_months_daily' => [
                1000,
                1000 + 16070400000 - 1,
                'D',
            ],
            'under_15_months_weekly' => [
                1000,
                1000 + 40176000000 - 1,
                'W',
            ],
            'under_5_years_monthly' => [
                1000,
                1000 + 157680000000 - 1,
                'M',
            ],
            'over_5_years_yearly' => [
                1000,
                1000 + 157680000000,
                'Y',
            ],
            'zero_range_minute' => [
                1000,
                1000,
                'm',
            ],
        ];
    }

    /**
     * @dataProvider resolutionProvider
     */
    public function testItShouldCalculateCorrectResolution(int $start, int $end, string $expected): void
    {
        $current = $start;
        $timeline = new EventsTimeline('[AR,all,1]', $start, $end, $current, $this->mockEventsApi);
        $this->assertEquals($expected, $timeline->getResolution());
    }
}
