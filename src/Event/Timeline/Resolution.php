<?php

namespace Helioviewer\Api\Event\Timeline;

/**
 * Pure function: calculates timeline resolution from a time range.
 */
class Resolution
{
    private const THRESHOLDS = [
        86400000 => 'm',        // < 1 day
        172800000 => '30m',     // < 2 days
        864000000 => 'h',       // < 10 days
        16070400000 => 'D',     // < ~6 months
        40176000000 => 'W',     // < ~15 months
        157680000000 => 'M',    // < ~5 years
    ];

    /**
     * @param int $rangeMs Time range in milliseconds
     * @return string Resolution code: m, 30m, h, D, W, M, Y
     */
    public static function fromRange(int $rangeMs): string
    {
        foreach (self::THRESHOLDS as $threshold => $resolution) {
            if ($rangeMs < $threshold) {
                return $resolution;
            }
        }
        return 'Y';
    }
}
