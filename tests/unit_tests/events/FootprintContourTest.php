<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\FootprintContour;
use Helioviewer\Api\Event\Shape;

final class FootprintContourTest extends TestCase
{
    /** A single HPC point; pass $behind=true to flag it far-side (visible:false). */
    private static function pt(float $x, float $y, bool $behind = false): array
    {
        $p = ['x' => $x, 'y' => $y];
        if ($behind) {
            $p['visible'] = false;
        }
        return $p;
    }

    /**
     * n points evenly spaced on a circle of radius $r (CCW, angle increasing).
     * $behindIdx flags the given indices as far-side.
     *
     * @param int[] $behindIdx
     */
    private static function circle(int $n, float $r, array $behindIdx = []): array
    {
        $behind = array_fill_keys($behindIdx, true);
        $points = [];
        for ($i = 0; $i < $n; $i++) {
            $th = $i * (2 * M_PI / $n);
            $points[] = self::pt($r * cos($th), $r * sin($th), isset($behind[$i]));
        }
        return $points;
    }

    // --- classification -----------------------------------------------------

    public function testFrontContourYieldsOneFillAndNothingElse(): void
    {
        $c = new FootprintContour(self::circle(8, 300.0));
        $this->assertTrue($c->isFront());
        $this->assertFalse($c->isPartial());

        $shapes = $c->shapes();
        $this->assertCount(1, $shapes['fills']);
        $this->assertSame([], $shapes['ghosts']);
        $this->assertSame([], $shapes['tints']);
        $this->assertInstanceOf(Shape::class, $shapes['fills'][0]);
        $this->assertTrue($shapes['fills'][0]->isFill());
        $this->assertTrue($shapes['fills'][0]->closed);
        $this->assertSame(8, $shapes['fills'][0]->count());
    }

    public function testBehindContourYieldsOneGhostAndOneTintNoFill(): void
    {
        $c = new FootprintContour(self::circle(8, 300.0, [0, 1, 2, 3, 4, 5, 6, 7]));
        $this->assertTrue($c->isBehind());
        $this->assertFalse($c->isPartial());

        $shapes = $c->shapes();
        $this->assertSame([], $shapes['fills']);
        $this->assertCount(1, $shapes['ghosts']);
        $this->assertCount(1, $shapes['tints']);
        $this->assertTrue($shapes['ghosts'][0]->closed);
        $this->assertTrue($shapes['tints'][0]->closed);
    }

    // --- runs / wrap-merge --------------------------------------------------

    /**
     * The documented example: F F B B B B F F F F. The near-side arc listed as
     * (6..9) + (0,1) must merge, tail before head, into ONE run in walking order
     * (6,7,8,9,0,1); the behind arc (2,3,4,5) is the other run.
     */
    public function testRunsWrapMergeKeepsNearSideArcWhole(): void
    {
        $points = self::circle(10, 900.0, [2, 3, 4, 5]);
        $runs = (new FootprintContour($points))->runs();

        $this->assertCount(2, $runs);

        // run 0: the merged near-side arc
        $this->assertFalse($runs[0]['behindSun']);
        $this->assertSame(6, $runs[0]['first']);
        $this->assertSame(1, $runs[0]['last']);
        $this->assertSame(6, count($runs[0]['points']));

        // run 1: the behind arc
        $this->assertTrue($runs[1]['behindSun']);
        $this->assertSame(2, $runs[1]['first']);
        $this->assertSame(5, $runs[1]['last']);
        $this->assertSame(4, count($runs[1]['points']));
    }

    public function testRunsNoMergeWhenEndsDiffer(): void
    {
        // F B B ... B F ... : first run front, last run behind -> no merge.
        $points = self::circle(6, 900.0, [1, 2, 3]);
        $runs = (new FootprintContour($points))->runs();
        // runs: [F(0)], [B(1,2,3)], [F(4,5)] -> ends differ (F vs F)? idx0 F, idx5 F
        // Actually 0 F, 1-3 B, 4-5 F -> first F, last F -> merges into 2.
        $this->assertCount(2, $runs);
    }

    // --- straddler shapes ---------------------------------------------------

    public function testStraddlerYieldsOneFillOneGhostOneTint(): void
    {
        // One behind run (single limb crossing) -> exactly one of each kind.
        $c = new FootprintContour(self::circle(10, 900.0, [2, 3, 4, 5]));
        $this->assertTrue($c->isPartial());

        $shapes = $c->shapes();
        $this->assertCount(1, $shapes['ghosts'], 'one behind run -> one ghost');
        $this->assertCount(1, $shapes['fills'],  'in-front runs chain into one fill');
        $this->assertCount(1, $shapes['tints'],  'behind runs chain into one tint');

        // The ghost is an OPEN polyline extended by one near-side neighbour at
        // each end (4 behind points + 2 neighbours = 6).
        $this->assertFalse($shapes['ghosts'][0]->closed);
        $this->assertSame(6, $shapes['ghosts'][0]->count());

        // Fills/tints are closed and non-trivial.
        $this->assertTrue($shapes['fills'][0]->closed);
        $this->assertGreaterThanOrEqual(3, $shapes['fills'][0]->count());
        $this->assertTrue($shapes['tints'][0]->closed);
        $this->assertGreaterThanOrEqual(3, $shapes['tints'][0]->count());
    }

    public function testStraddlerWithTwoBehindRunsYieldsTwoGhosts(): void
    {
        // F B B F F F B B F F : crosses the limb twice -> two behind runs,
        // hence two ghosts; the two in-front runs chain (via limb arcs) into a
        // single fill polygon with a notch.
        $c = new FootprintContour(self::circle(10, 900.0, [1, 2, 6, 7]));
        $this->assertTrue($c->isPartial());

        $shapes = $c->shapes();
        $this->assertCount(2, $shapes['ghosts']);
        $this->assertNotEmpty($shapes['fills']);
        $this->assertNotEmpty($shapes['tints']);
    }

    // --- regionSide ---------------------------------------------------------

    public function testRegionSideFlipsWhenWalkReverses(): void
    {
        $ccw = self::circle(12, 300.0);           // angle increasing
        $cw  = array_reverse($ccw);               // same loop, opposite walk

        $sideCcw = (new FootprintContour($ccw))->regionSide();
        $sideCw  = (new FootprintContour($cw))->regionSide();

        $this->assertContains($sideCcw, [-1, 1]);
        $this->assertSame(-$sideCcw, $sideCw, 'reversing the walk flips the region side');
    }

    public function testRegionSideIsLeftForNearSideCcwLoop(): void
    {
        // A small near-side loop walked counter-clockwise encloses its area on
        // the LEFT of the walk -> +1.
        $this->assertSame(1, (new FootprintContour(self::circle(12, 300.0)))->regionSide());
    }

    /**
     * Regression: a degenerate 2-point all-behind contour, seen in real WSA CH
     * data (e.g. Coronal Hole >> AGONG >> R11). It classifies as isBehind, so
     * shapes() yields one closed ghost + one closed tint, each with 2 points.
     * The renderer must NOT hand a 2-point *closed* shape to ImagickDraw::polygon
     * (which needs >=3 points, else "non-conforming primitive") — it downgrades
     * such a ghost to a polyline and skips the 2-point tint.
     */
    public function testTwoPointBehindContourClassifiesAsBehind(): void
    {
        $c = new FootprintContour([
            self::pt(900.0, 100.0, true),
            self::pt(880.0, 140.0, true),
        ]);
        $this->assertTrue($c->isBehind());

        $s = $c->shapes();
        $this->assertCount(1, $s['ghosts']);
        $this->assertCount(1, $s['tints']);
        $this->assertSame(2, $s['ghosts'][0]->count());
        $this->assertTrue($s['ghosts'][0]->closed);
        $this->assertSame([], $s['fills']);
    }

    // --- misc ---------------------------------------------------------------

    public function testEveryShapeIsAShapeObjectAndGroupMatchesKind(): void
    {
        $expectedKind = [
            'tints'  => Shape::TINT,
            'ghosts' => Shape::GHOST,
            'fills'  => Shape::FILL,
        ];
        $shapes = (new FootprintContour(self::circle(10, 900.0, [2, 3, 4, 5])))->shapes();
        foreach ($expectedKind as $group => $kind) {
            foreach ($shapes[$group] as $shape) {
                $this->assertInstanceOf(Shape::class, $shape);
                $this->assertSame($kind, $shape->kind);           // the group matches the Shape's kind
                $this->assertIsBool($shape->closed);
                $this->assertNotEmpty($shape->points);
                $this->assertSame(count($shape->points), $shape->count());
                foreach ($shape->points as $p) {
                    $this->assertArrayHasKey('x', $p);
                    $this->assertArrayHasKey('y', $p);
                    $this->assertIsFloat($p['x']);
                    $this->assertIsFloat($p['y']);
                }
            }
        }
    }
}
