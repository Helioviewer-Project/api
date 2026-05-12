<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\Resolution;

final class ResolutionTest extends TestCase
{
    public static function resolutionProvider(): array
    {
        return [
            'zero_range' => [0, 'm'],
            'one_second' => [1000, 'm'],
            'one_hour' => [3600000, 'm'],
            'just_under_1_day' => [86400000 - 1, 'm'],
            'exactly_1_day' => [86400000, '30m'],
            'just_under_2_days' => [172800000 - 1, '30m'],
            'exactly_2_days' => [172800000, 'h'],
            'just_under_10_days' => [864000000 - 1, 'h'],
            'exactly_10_days' => [864000000, 'D'],
            'just_under_6_months' => [16070400000 - 1, 'D'],
            'exactly_6_months' => [16070400000, 'W'],
            'just_under_15_months' => [40176000000 - 1, 'W'],
            'exactly_15_months' => [40176000000, 'M'],
            'just_under_5_years' => [157680000000 - 1, 'M'],
            'exactly_5_years' => [157680000000, 'Y'],
            'ten_years' => [315360000000, 'Y'],
        ];
    }

    /**
     * @dataProvider resolutionProvider
     */
    public function testItShouldReturnCorrectResolution(int $rangeMs, string $expected): void
    {
        $this->assertEquals($expected, Resolution::fromRange($rangeMs));
    }
}
