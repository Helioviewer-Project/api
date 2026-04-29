<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Timeline;

use InvalidArgumentException;

/**
 * Value object for a timeline time range.
 * Stores both the visible range and the extended range (1x buffer each side).
 * All timestamps are in milliseconds.
 */
class TimeRange
{
    private int $start;
    private int $end;
    private int $current;
    private int $extendedStart;
    private int $extendedEnd;

    /**
     * @param int $start Visible start in milliseconds
     * @param int $end Visible end in milliseconds
     * @param int $current Current observation time in milliseconds
     * @throws InvalidArgumentException If timestamps are not integers or start >= end
     */
    public function __construct($start, $end, $current)
    {
        $start = filter_var($start, FILTER_VALIDATE_INT);
        $end = filter_var($end, FILTER_VALIDATE_INT);
        $current = filter_var($current, FILTER_VALIDATE_INT);

        if ($start === false || $end === false || $current === false) {
            throw new InvalidArgumentException('start, end, and current must be integer timestamps in milliseconds');
        }

        if ($start >= $end) {
            throw new InvalidArgumentException('start must be less than end');
        }

        $this->start = $start;
        $this->end = $end;
        $this->current = $current;

        $distance = $end - $start;
        $this->extendedStart = $start - $distance;
        $this->extendedEnd = $end + $distance;
    }

    // Visible range
    public function start(): int { return $this->start; }
    public function end(): int { return $this->end; }
    public function current(): int { return $this->current; }
    public function range(): int { return $this->end - $this->start; }
    public function startSec(): int { return intval($this->start / 1000); }
    public function endSec(): int { return intval($this->end / 1000); }

    // Extended range (1x buffer each side)
    public function extendedStart(): int { return $this->extendedStart; }
    public function extendedEnd(): int { return $this->extendedEnd; }
    public function extendedStartSec(): int { return intval($this->extendedStart / 1000); }
    public function extendedEndSec(): int { return intval($this->extendedEnd / 1000); }
}
