<?php

namespace Helioviewer\Api\Event\Timeline;

use InvalidArgumentException;

/**
 * Value object for a timeline time range.
 * All timestamps are in milliseconds.
 */
class TimeRange
{
    private int $startMs;
    private int $endMs;
    private int $currentMs;

    /**
     * @param int $startTimestamp Start time in milliseconds
     * @param int $endTimestamp End time in milliseconds
     * @param int $currentTimestamp Current observation time in milliseconds
     * @throws InvalidArgumentException If timestamps are not integers or start > end
     */
    public function __construct($startTimestamp, $endTimestamp, $currentTimestamp)
    {
        $startTimestamp = filter_var($startTimestamp, FILTER_VALIDATE_INT);
        $endTimestamp = filter_var($endTimestamp, FILTER_VALIDATE_INT);
        $currentTimestamp = filter_var($currentTimestamp, FILTER_VALIDATE_INT);

        if ($startTimestamp === false || $endTimestamp === false || $currentTimestamp === false) {
            throw new InvalidArgumentException('startTimestamp, endTimestamp, and currentTimestamp must be integer timestamps in milliseconds');
        }

        if ($startTimestamp >= $endTimestamp) {
            throw new InvalidArgumentException('startTimestamp must be less than endTimestamp');
        }

        $this->startMs = $startTimestamp;
        $this->endMs = $endTimestamp;
        $this->currentMs = $currentTimestamp;
    }

    public function start(): int { return $this->startMs; }
    public function end(): int { return $this->endMs; }
    public function current(): int { return $this->currentMs; }
    public function range(): int { return $this->endMs - $this->startMs; }
    public function startSec(): int { return intval($this->startMs / 1000); }
    public function endSec(): int { return intval($this->endMs / 1000); }

    /**
     * Returns an extended TimeRange with 1x buffer on each side for smooth scrolling.
     */
    public function extended(): self
    {
        $distance = $this->range();
        return new self(
            $this->startMs - $distance,
            $this->endMs + $distance,
            $this->currentMs
        );
    }
}
