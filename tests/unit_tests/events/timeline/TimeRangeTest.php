<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\TimeRange;

final class TimeRangeTest extends TestCase
{
    public static function invalidTimestampProvider(): array
    {
        return [
            'null_start' => [null, 2000, 1500],
            'null_end' => [1000, null, 1500],
            'null_current' => [1000, 2000, null],
            'string_start' => ['abc', 2000, 1500],
            'string_end' => [1000, 'abc', 1500],
            'string_current' => [1000, 2000, 'abc'],
            'float_start' => [10.5, 2000, 1500],
            'start_equals_end' => [1000, 1000, 1000],
            'start_greater_than_end' => [2000, 1000, 1500],
        ];
    }

    /**
     * @dataProvider invalidTimestampProvider
     */
    public function testItShouldThrowForInvalidTimestamps($start, $end, $current): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TimeRange($start, $end, $current);
    }

    public function testItShouldAllowNegativeTimestamps(): void
    {
        $range = new TimeRange(-2000, -1000, -1500);
        $this->assertEquals(-2000, $range->start());
        $this->assertEquals(-1000, $range->end());
        $this->assertEquals(-1500, $range->current());
    }

    public function testItShouldStoreTimestamps(): void
    {
        $range = new TimeRange(1000, 2000, 1500);
        $this->assertEquals(1000, $range->start());
        $this->assertEquals(2000, $range->end());
        $this->assertEquals(1500, $range->current());
    }

    public function testItShouldCalculateRange(): void
    {
        $range = new TimeRange(1000, 5000, 3000);
        $this->assertEquals(4000, $range->range());
    }

    public function testItShouldConvertToSeconds(): void
    {
        $range = new TimeRange(1500000, 3500000, 2500000);
        $this->assertEquals(1500, $range->startSec());
        $this->assertEquals(3500, $range->endSec());
    }

    public function testItShouldCalculateExtendedRange(): void
    {
        // Range: 1000 to 3000, distance = 2000
        // Extended: 1000-2000 = -1000, 3000+2000 = 5000
        $range = new TimeRange(1000, 3000, 2000);
        $this->assertEquals(-1000, $range->extendedStart());
        $this->assertEquals(5000, $range->extendedEnd());
    }

    public function testItShouldConvertExtendedToSeconds(): void
    {
        $range = new TimeRange(10000, 20000, 15000);
        // distance = 10000, extended: 0 to 30000
        $this->assertEquals(0, $range->extendedStartSec());
        $this->assertEquals(30, $range->extendedEndSec());
    }

    public function testExtendedRangeShouldBeTripleWidth(): void
    {
        $range = new TimeRange(1000, 5000, 3000);
        $extendedWidth = $range->extendedEnd() - $range->extendedStart();
        // Original: 4000. Extended: 3x = 12000
        $this->assertEquals(12000, $extendedWidth);
    }
}
