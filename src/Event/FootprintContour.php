<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * One footprint contour: a closed polygon of HPC points (arcsec), each point
 * possibly flagged behind the sun by the events API (point['visible'] === false).
 *
 * A faithful port of the web client's FootprintContour + limbArc
 * (helioviewer.org: resources/js/Events/EventMarker.js) so the server-side
 * renderer (screenshots, movies) draws footprints the way the browser does:
 * near-side area as fills closed along the limb, far-side area as a faint tint
 * under a dashed "ghost" outline.
 *
 * Pure geometry: operates on {x, y, visible?} arrays only -- no Imagick, no ROI,
 * no pixel scale. shapes() returns draw-ready contour shapes still in HPC
 * arcsec; the caller projects them to pixels and paints them in the order
 * tints -> ghosts -> fills.
 *
 * Everything a contour can say about itself is computed once and memoized:
 * which side of the sun it is on (isFront / isBehind / isPartial), its runs,
 * which side of the walk the region lies on (regionSide), and its shapes.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class FootprintContour
{
    private const TWO_PI  = 2 * M_PI;
    private const FOUR_PI = 4 * M_PI;

    /** @var array<int, array{x: float, y: float, visible?: bool}> */
    private array $points;
    private int $behindCount;

    /** @var array|null memoized runs() */
    private ?array $runs = null;
    /** @var int|null memoized regionSide() */
    private ?int $side = null;
    /** @var array|null memoized shapes() */
    private ?array $shapes = null;

    /**
     * @param array<int, array{x: float, y: float, visible?: bool}> $points
     *        Closed polygon of HPC-arcsec points. A point is behind the sun when
     *        its 'visible' key is === false; the key is absent for near-side points.
     */
    public function __construct(array $points)
    {
        $this->points = array_values($points);
        $this->behindCount = 0;
        foreach ($this->points as $p) {
            if (self::isPointBehindSun($p)) {
                $this->behindCount++;
            }
        }
    }

    /**
     * True when a footprint point is behind the sun. The API sends visible:false
     * for far-side points and omits the key for near-side ones, so the test is
     * strictly "=== false". (Unrelated to the marker/label visibility toggles.)
     */
    public static function isPointBehindSun(array $point): bool
    {
        return ($point['visible'] ?? null) === false;
    }

    public function length(): int
    {
        return count($this->points);
    }

    /** Every point on the near side: drawn as one plain fill. */
    public function isFront(): bool
    {
        return $this->behindCount === 0;
    }

    /** Every point behind the sun: drawn as a tint under a closed dashed outline. */
    public function isBehind(): bool
    {
        return $this->behindCount === count($this->points);
    }

    /** Straddles the limb: cut into runs; fills closed along the limb, far side ghosted and tinted. */
    public function isPartial(): bool
    {
        return !$this->isFront() && !$this->isBehind();
    }

    /**
     * The contour's RUNS, computed once. A run is a stretch of consecutive
     * points all on the same side of the sun. Walking the contour, a new run
     * starts every time the behind-sun flag flips. Because the contour is
     * closed, a near-side arc that the API happened to list starting in its
     * middle shows up as a first and a last run of the same side; the wrap step
     * merges them (tail before head) so the arc stays one run in walking order.
     *
     * @return array<int, array{behindSun: bool, points: array, first: int, last: int}>
     */
    public function runs(): array
    {
        if ($this->runs === null) {
            $runs = [];
            foreach ($this->points as $index => $point) {
                $behind = self::isPointBehindSun($point);
                $lastIdx = count($runs) - 1;
                if ($lastIdx >= 0 && $runs[$lastIdx]['behindSun'] === $behind) {
                    // same side as the previous point: extend the current run
                    $runs[$lastIdx]['points'][] = $point;
                    $runs[$lastIdx]['last'] = $index;
                } else {
                    // the flag flipped (or this is the first point): start a new run
                    $runs[] = ['behindSun' => $behind, 'points' => [$point], 'first' => $index, 'last' => $index];
                }
            }
            // Wrap-around: if the list started in the middle of an arc, the first
            // and last runs are the same arc. Merge them, tail before head.
            $count = count($runs);
            if ($count > 1 && $runs[0]['behindSun'] === $runs[$count - 1]['behindSun']) {
                $tail = array_pop($runs);
                $runs[0]['points'] = array_merge($tail['points'], $runs[0]['points']);
                $runs[0]['first'] = $tail['first'];
            }
            $this->runs = $runs;
        }
        return $this->runs;
    }

    /**
     * Which side of the walk the region is on, computed once. A closed curve on
     * a sphere bounds two complementary areas; the region is the SMALLER one.
     * The contour is rebuilt in 3D (z = +sqrt(R^2-r^2) in front, - behind;
     * R = the contour's largest radius, i.e. the limb) and the signed solid
     * angle of the fan of spherical triangles from a reference direction is
     * summed (Van Oosterom & Strackee). Reduced to [0, 4*pi) that is the area on
     * the LEFT of the walk; if it is the smaller half the region is on the left.
     *
     * @return int +1 region on the left of the walking direction, -1 on the right
     */
    public function regionSide(): int
    {
        if ($this->side === null) {
            $R = 0.0;
            foreach ($this->points as $p) {
                $r = hypot($p['x'], $p['y']);
                if ($r > $R) {
                    $R = $r;
                }
            }
            if ($R <= 0.0) {
                $R = 1.0;
            }

            $u = [];
            foreach ($this->points as $p) {
                $x = $p['x'] / $R;
                $y = $p['y'] / $R;
                $z = sqrt(max(0.0, 1 - $x * $x - $y * $y));
                $u[] = [$x, $y, self::isPointBehindSun($p) ? -$z : $z];
            }

            // reference direction: the normalized mean of the points, else Earth
            $ref = [0.0, 0.0, 0.0];
            foreach ($u as $v) {
                $ref[0] += $v[0];
                $ref[1] += $v[1];
                $ref[2] += $v[2];
            }
            $norm = sqrt($ref[0] * $ref[0] + $ref[1] * $ref[1] + $ref[2] * $ref[2]);
            $ref = $norm > 1e-6
                ? [$ref[0] / $norm, $ref[1] / $norm, $ref[2] / $norm]
                : [0.0, 0.0, 1.0];

            $dot = fn(array $a, array $b): float => $a[0] * $b[0] + $a[1] * $b[1] + $a[2] * $b[2];
            $triple = fn(array $a, array $b, array $c): float =>
                  $a[0] * ($b[1] * $c[2] - $b[2] * $c[1])
                - $a[1] * ($b[0] * $c[2] - $b[2] * $c[0])
                + $a[2] * ($b[0] * $c[1] - $b[1] * $c[0]);

            $omega = 0.0;
            $count = count($u);
            for ($i = 0; $i < $count; $i++) {
                $b = $u[$i];
                $c = $u[($i + 1) % $count];
                $omega += 2 * atan2($triple($ref, $b, $c), 1 + $dot($ref, $b) + $dot($b, $c) + $dot($c, $ref));
            }
            $leftArea = self::posmod($omega, self::FOUR_PI);
            $this->side = $leftArea <= self::TWO_PI ? 1 : -1;
        }
        return $this->side;
    }

    /**
     * The SHAPES to draw for this contour, computed once.
     *
     * @return array{tints: Shape[], ghosts: Shape[], fills: Shape[]} the far-side
     *         tints, the far-side ghosts and the near-side fills, each a list of Shape.
     */
    public function shapes(): array
    {
        if ($this->shapes === null) {
            $this->shapes = $this->computeShapes();
        }
        return $this->shapes;
    }

    private function computeShapes(): array
    {
        $fills = [];
        $ghosts = [];
        $tints = [];

        if ($this->isFront()) {
            // whole contour on the near side: one filled polygon
            $fills[] = new Shape(Shape::FILL, true, $this->points);
            return ['tints' => $tints, 'ghosts' => $ghosts, 'fills' => $fills];
        }
        if ($this->isBehind()) {
            // whole contour behind the sun: one dashed closed outline, tinted inside
            $ghosts[] = new Shape(Shape::GHOST, true, $this->points);
            $tints[]  = new Shape(Shape::TINT,  true, $this->points);
            return ['tints' => $tints, 'ghosts' => $ghosts, 'fills' => $fills];
        }

        // Straddler: split into runs. Each behind run becomes its own ghost; the
        // in-front runs become fills closed along the limb.
        $n = count($this->points);
        $runs = $this->runs();
        $runCount = count($runs);
        $side = $this->regionSide();

        $ccw = fn(float $from, float $to): float => self::posmod($to - $from, self::TWO_PI);
        // signed sweep from angle $from to $to going the region's way round the limb
        $sweepTo = fn(float $from, float $to): float =>
            $side > 0 ? $ccw($from, $to) : -(self::TWO_PI - $ccw($from, $to));

        // every place an in-front run enters or leaves the disk, with its limb angle
        $crossings = [];
        foreach ($runs as $idx => $run) {
            if (!$run['behindSun']) {
                $rp = $run['points'];
                $crossings[] = ['th' => self::angle($rp[0]), 'entry' => true, 'run' => $idx];
                $crossings[] = ['th' => self::angle($rp[count($rp) - 1]), 'entry' => false, 'run' => $idx];
            }
        }

        $nextRun  = []; // in-front run index -> the run its limb arc leads to
        $arcAfter = []; // in-front run index -> limb arc points after its last point
        $extended = []; // behind run index -> { before, after, points }

        foreach ($runs as $idx => $run) {
            if ($run['behindSun']) {
                // Behind arc: dashed open line, EXTENDED by the in-front neighbour
                // at each end so it starts/ends exactly on the fill's edge.
                $before = $this->points[($run['first'] - 1 + $n) % $n];
                $after  = $this->points[($run['last'] + 1) % $n];
                $extended[$idx] = [
                    'before' => $before,
                    'after'  => $after,
                    'points' => array_merge([$before], $run['points'], [$after]),
                ];
                $ghosts[] = new Shape(Shape::GHOST, false, $extended[$idx]['points']);
                continue;
            }
            // In-front run: from its last point, walk the limb the region's way
            // round to the nearest crossing; that is where the visible boundary
            // continues.
            $rp = $run['points'];
            $last = $rp[count($rp) - 1];
            $thLast = self::angle($last);
            // skip the crossing we are leaving from
            $best = self::nearestCrossing(
                $thLast, $crossings, $sweepTo,
                fn(array $c): bool => $c['run'] === $idx && !$c['entry']
            );
            $target = $idx;
            if ($best !== null && $best['crossing']['entry']) {
                $target = $best['crossing']['run'];
                $sweep = $best['sweep'];
            } else {
                // not a re-entry (open, seam-cut contour): close this run on itself the short way
                $s = $ccw($thLast, self::angle($rp[0]));
                $sweep = $s <= M_PI ? $s : -(self::TWO_PI - $s);
            }
            $nextRun[$idx]  = $target;
            $arcAfter[$idx] = self::limbArc($last, $runs[$target]['points'][0], $sweep);
        }

        // Chain run -> arc -> next run -> ... into closed polygons.
        $done = [];
        foreach ($runs as $idx => $run) {
            if ($run['behindSun'] || !empty($done[$idx])) {
                continue;
            }
            $points = [];
            $i = $idx;
            $guard = 0;
            do {
                $done[$i] = true;
                $points = array_merge($points, $runs[$i]['points'], $arcAfter[$i]);
                $i = $nextRun[$i];
                $guard++;
            } while ($i !== $idx && empty($done[$i]) && $guard < $runCount);
            if (count($points) >= 3) {
                $fills[] = new Shape(Shape::FILL, true, $points);
            }
        }

        // Far-side tint: the region clipped to the FAR hemisphere, built like the
        // fills but mirrored. From where a behind run comes back in front (its
        // `after`) the boundary follows the limb the OTHER way round to the
        // nearest crossing (some behind run's `before`). Chain into polygons.
        $sweepBack = fn(float $from, float $to): float =>
            $side > 0 ? -(self::TWO_PI - $ccw($from, $to)) : $ccw($from, $to);

        $farCrossings = [];
        foreach ($extended as $idx => $ex) {
            $farCrossings[] = ['th' => self::angle($ex['before']), 'exit' => true,  'run' => $idx];
            $farCrossings[] = ['th' => self::angle($ex['after']),  'exit' => false, 'run' => $idx];
        }
        $nextBehind = [];
        $arcBehind  = [];
        foreach ($extended as $idx => $ex) {
            $after = $ex['after'];
            $thAfter = self::angle($after);
            // skip the crossing we are leaving from
            $best = self::nearestCrossing(
                $thAfter, $farCrossings, $sweepBack,
                fn(array $c): bool => $c['run'] === $idx && !$c['exit']
            );
            $target = $idx;
            if ($best !== null && $best['crossing']['exit']) {
                $target = $best['crossing']['run'];
                $sweep = $best['sweep'];
            } else {
                // not an exit (open, seam-cut contour): close this run's tint on itself the short way
                $s = $ccw($thAfter, self::angle($ex['before']));
                $sweep = $s <= M_PI ? $s : -(self::TWO_PI - $s);
            }
            $nextBehind[$idx] = $target;
            $arcBehind[$idx]  = self::limbArc($after, $extended[$target]['before'], $sweep);
        }
        $doneBehind = [];
        foreach ($extended as $idx => $ex) {
            if (!empty($doneBehind[$idx])) {
                continue;
            }
            $points = [];
            $i = $idx;
            $guard = 0;
            do {
                $doneBehind[$i] = true;
                $points = array_merge($points, $extended[$i]['points'], $arcBehind[$i]);
                $i = $nextBehind[$i];
                $guard++;
            } while ($i !== $idx && empty($doneBehind[$i]) && $guard < $runCount);
            if (count($points) >= 3) {
                $tints[] = new Shape(Shape::TINT, true, $points);
            }
        }

        return ['tints' => $tints, 'ghosts' => $ghosts, 'fills' => $fills];
    }

    /**
     * Intermediate points along the solar limb between crossing points $a and
     * $b, so a fill is closed along the limb instead of with a straight chord.
     * The radius is interpolated from |a| to |b|, so no solar-radius constant is
     * needed. About one point every 2 degrees.
     *
     * @return array<int, array{x: float, y: float}> intermediate points only
     *         (a and b themselves are not repeated)
     */
    private static function limbArc(array $a, array $b, float $sweep): array
    {
        if (abs($sweep) < 1e-6) {
            return [];
        }
        $thA = atan2($a['y'], $a['x']);
        $rA = hypot($a['x'], $a['y']);
        $rB = hypot($b['x'], $b['y']);
        $steps = max(1, (int) round(abs($sweep) / (M_PI / 90)));
        $points = [];
        for ($i = 1; $i < $steps; $i++) {
            $t = $i / $steps;
            $th = $thA + $sweep * $t;
            $r = $rA + ($rB - $rA) * $t;
            $points[] = ['x' => $r * cos($th), 'y' => $r * sin($th)];
        }
        return $points;
    }

    /**
     * Nearest crossing from angle $from, walking the limb in the direction given
     * by $sweepFn (a signed sweep function).
     *
     * Half-turn rule (spec section 8e): search the region's way round for the
     * smallest |sweep|; if that nearest crossing is more than half a turn away
     * (|sweep| > pi), it is wrong -- no visible region straddles more than half
     * the limb -- so re-run the same search the OTHER way round and take that.
     * This covers a seam-cut contour whose fake closing edge flips the region
     * side (its near-side sliver would otherwise close the long way and fill the
     * disk), and two crossings a hair apart landing in the wrong angular order
     * through coordinate noise. It subsumes the earlier 2-degree guard.
     *
     * @param callable $sweepFn fn(float $from, float $to): float  signed sweep, region's way
     * @param callable $skip    fn(array $crossing): bool          crossing to ignore
     * @return array{crossing: array, sweep: float}|null null when there is no other crossing
     */
    private static function nearestCrossing(float $from, array $crossings, callable $sweepFn, callable $skip): ?array
    {
        $best = self::searchCrossings($from, $crossings, $sweepFn, $skip);
        if ($best !== null && abs($best['sweep']) > M_PI) {
            // The nearest hit the region's way is beyond a half turn -> wrong.
            // Walk the other way: the complementary sweep in the opposite direction.
            $otherWay = function (float $a, float $b) use ($sweepFn): float {
                $s = $sweepFn($a, $b);
                return -($s <=> 0) * (self::TWO_PI - abs($s));
            };
            $best = self::searchCrossings($from, $crossings, $otherWay, $skip);
        }
        return $best;
    }

    /**
     * Smallest-|sweep| crossing under a given signed-sweep function.
     *
     * @return array{crossing: array, sweep: float}|null
     */
    private static function searchCrossings(float $from, array $crossings, callable $sweepFn, callable $skip): ?array
    {
        $best = null;
        foreach ($crossings as $c) {
            if ($skip($c)) {
                continue;
            }
            $sweep = $sweepFn($from, $c['th']);
            if ($best === null || abs($sweep) < abs($best['sweep'])) {
                $best = ['crossing' => $c, 'sweep' => $sweep];
            }
        }
        return $best;
    }

    private static function angle(array $p): float
    {
        return atan2($p['y'], $p['x']);
    }

    /** Positive modulo, matching JS ((x % m) + m) % m. */
    private static function posmod(float $x, float $m): float
    {
        return fmod(fmod($x, $m) + $m, $m);
    }
}
