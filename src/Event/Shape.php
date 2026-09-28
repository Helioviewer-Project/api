<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * One draw-ready shape produced by FootprintContour::shapes(): a near-side FILL,
 * a far-side TINT, or a dashed far-side GHOST. Pure data in HPC arcsec — the
 * renderer projects the points to pixels and paints them (tints, then ghosts,
 * then fills). `closed` distinguishes a closed polygon from an open polyline
 * (a straddler's ghost run is open; everything else is closed).
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
final class Shape
{
    public const TINT  = 'tint';
    public const GHOST = 'ghost';
    public const FILL  = 'fill';

    /**
     * @param self::TINT|self::GHOST|self::FILL      $kind
     * @param bool                                   $closed  closed polygon vs open polyline
     * @param array<int, array{x: float, y: float}>  $points  HPC arcsec
     */
    public function __construct(
        public readonly string $kind,
        public readonly bool $closed,
        public readonly array $points
    ) {
    }

    public function isTint(): bool
    {
        return $this->kind === self::TINT;
    }

    public function isGhost(): bool
    {
        return $this->kind === self::GHOST;
    }

    public function isFill(): bool
    {
        return $this->kind === self::FILL;
    }

    /** Number of points in the shape. */
    public function count(): int
    {
        return count($this->points);
    }
}
