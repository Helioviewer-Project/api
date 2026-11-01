<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventRegionSvg;
use Helioviewer\Api\Event\SvgRasterizer;

final class EventRegionSvgTest extends TestCase
{
    /** A 3-point pixel polygon, shifted by ($dx, $dy). */
    private function tri(float $dx = 0.0, float $dy = 0.0): array
    {
        return [['x' => 0.0 + $dx, 'y' => 0.0 + $dy], ['x' => 10.0 + $dx, 'y' => 0.0 + $dy], ['x' => 10.0 + $dx, 'y' => 10.0 + $dy]];
    }

    public function testEmptyProducesTheThreeGroupsAtTheRightSize(): void
    {
        $svg = (new EventRegionSvg([], 100, 80))->toSvg();
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('width="100"', $svg);
        $this->assertStringContainsString('height="80"', $svg);
        $this->assertStringContainsString('<g opacity="0.18">', $svg); // the flat tint layer
        $this->assertStringNotContainsString('<clipPath', $svg);
    }

    public function testEventWithFillsClipsTintAndGhostAndHasAllStyles(): void
    {
        $rendered = [[
            'hex'    => 'FEF38E',
            'tints'  => [$this->tri()],
            'ghosts' => [['pts' => $this->tri(), 'closed' => false]],
            'fills'  => [$this->tri()],
        ]];
        $svg = (new EventRegionSvg($rendered, 200, 150))->toSvg();

        // clip paths, never masks (masks are what made rasterising slow)
        $this->assertStringNotContainsString('<mask', $svg);
        // "outside fill 0": the canvas with the fill cut out, even-odd
        $this->assertStringContainsString('<clipPath id="o0_0"><path clip-rule="evenodd" d="M0 0H200V150H0Z M0,0 10,0 10,10Z"/></clipPath>', $svg);
        // the tint sits in the flat 0.18 group, clipped to outside its own fill
        $this->assertStringContainsString('<g opacity="0.18"><g clip-path="url(#o0_0)"><polygon', $svg);
        // ghost: 0.55 outside the fill, 45% strength (0.248) inside it
        $this->assertStringContainsString('<clipPath id="in0"><polygon points="0,0 10,0 10,10"/></clipPath>', $svg);
        $this->assertStringContainsString('<g clip-path="url(#in0)"><polyline', $svg);
        $this->assertStringContainsString('stroke-opacity="0.248"', $svg);
        // an open ghost run -> polyline, dashed @ 0.55
        $this->assertStringContainsString('<polyline', $svg);
        $this->assertStringContainsString('stroke-opacity="0.55"', $svg);
        $this->assertStringContainsString('stroke-dasharray="5,4"', $svg);
        // fill @0.4 with black stroke @0.533, in the event colour
        $this->assertStringContainsString('fill-opacity="0.4"', $svg);
        $this->assertStringContainsString('stroke-opacity="0.533"', $svg);
        $this->assertStringContainsString('fill="#FEF38E"', $svg);
    }

    public function testEventWithoutFillsHasNoClipsAndClosedGhostIsPolygon(): void
    {
        // all-behind event: a tint + a closed ghost, no near-side fills → no clips.
        $rendered = [[
            'hex'    => 'B0C4FF',
            'tints'  => [$this->tri()],
            'ghosts' => [['pts' => $this->tri(), 'closed' => true]],
            'fills'  => [],
        ]];
        $svg = (new EventRegionSvg($rendered, 100, 100))->toSvg();

        $this->assertStringNotContainsString('<clipPath', $svg);
        $this->assertStringNotContainsString('clip-path="url', $svg);
        $this->assertStringContainsString('<polygon', $svg); // closed ghost (>=3 pts) -> polygon
    }

    public function testFillsFarFromTheTintAndGhostAreNotClipped(): void
    {
        // the fill is 100 px away: cutting it out of the tint/ghost cannot change a pixel
        $rendered = [[
            'hex'    => 'FEF38E',
            'tints'  => [$this->tri()],
            'ghosts' => [['pts' => $this->tri(), 'closed' => true]],
            'fills'  => [$this->tri(100.0, 100.0)],
        ]];
        $svg = (new EventRegionSvg($rendered, 200, 200))->toSvg();

        $this->assertStringNotContainsString('<clipPath', $svg);
        $this->assertStringContainsString('<g opacity="0.18"><polygon', $svg);
        $this->assertStringContainsString('stroke-opacity="0.55"', $svg);
        $this->assertStringNotContainsString('stroke-opacity="0.248"', $svg);
        $this->assertStringContainsString('fill-opacity="0.4"', $svg); // the fill itself is still drawn
    }

    public function testOverlappingFillsAreEachCutOutOfTheTint(): void
    {
        // two overlapping fill polygons touch the tint, a third is far away:
        // the tint is nested in one "outside" clip per touching polygon
        $rendered = [[
            'hex'    => 'FEF38E',
            'tints'  => [$this->tri()],
            'ghosts' => [],
            'fills'  => [$this->tri(), $this->tri(4.0, 0.0), $this->tri(150.0, 150.0)],
        ]];
        $svg = (new EventRegionSvg($rendered, 200, 200))->toSvg();

        $this->assertStringContainsString('<clipPath id="o0_0">', $svg);
        $this->assertStringContainsString('<clipPath id="o0_1">', $svg);
        $this->assertStringNotContainsString('<clipPath id="o0_2">', $svg);
        $this->assertStringContainsString('<g clip-path="url(#o0_1)"><g clip-path="url(#o0_0)"><polygon', $svg);
        $this->assertSame(3, substr_count($svg, 'fill-opacity="0.4"')); // all three fills drawn
    }

    public function testClipIdsAreUniquePerEvent(): void
    {
        $event = ['hex' => 'FEF38E', 'tints' => [$this->tri()], 'ghosts' => [['pts' => $this->tri(), 'closed' => false]], 'fills' => [$this->tri()]];
        $svg = (new EventRegionSvg([$event, $event], 100, 100))->toSvg();

        preg_match_all('/<clipPath id="([^"]+)"/', $svg, $m);
        $this->assertSame(['o0_0', 'in0', 'o1_0', 'in1'], $m[1]);
    }

    public function testAreaIsTheDrawnPartOfTheFrameWithAMargin(): void
    {
        // shapes span x 20..70 (ghost 15.4 .. fill 70), y 30..60: 3 px margin, out to whole pixels
        $rendered = [[
            'hex'    => 'FEF38E',
            'tints'  => [$this->tri(20.0, 30.0)],
            'ghosts' => [['pts' => [['x' => 15.4, 'y' => 35.0], ['x' => 40.0, 'y' => 60.0]], 'closed' => false]],
            'fills'  => [$this->tri(60.0, 40.0)],
        ]];
        $regions = new EventRegionSvg($rendered, 200, 150);
        $this->assertSame([12, 27, 61, 36], $regions->area());
        $this->assertStringStartsWith('<svg xmlns="http://www.w3.org/2000/svg" width="61" height="36" viewBox="12 27 61 36">', $regions->toSvg());
    }

    public function testAreaStaysInsideTheFrame(): void
    {
        // a fill running off the top-left and bottom-right corners
        $poly = [['x' => -50.0, 'y' => -20.0], ['x' => 250.0, 'y' => -20.0], ['x' => 250.0, 'y' => 170.0]];
        $regions = new EventRegionSvg([['hex' => 'FEF38E', 'tints' => [], 'ghosts' => [], 'fills' => [$poly]]], 200, 150);
        $this->assertSame([0, 0, 200, 150], $regions->area());
    }

    public function testAreaIsNullWhenNothingIsDrawnOnTheFrame(): void
    {
        $this->assertNull((new EventRegionSvg([], 100, 80))->area());
        $offFrame = ['hex' => 'FEF38E', 'tints' => [$this->tri(500.0, 500.0)], 'ghosts' => [], 'fills' => []];
        $this->assertNull((new EventRegionSvg([$offFrame], 100, 80))->area());
    }

    public function testRasterisingTheAreaGivesTheSamePixelsAsTheFullFrame(): void
    {
        $rasterizer = new SvgRasterizer();
        if (!$rasterizer->isAvailable()) {
            $this->markTestSkipped('rsvg-convert is not installed');
        }
        $circle = function (float $cx, float $cy, float $r, int $n = 40): array {
            $pts = [];
            for ($i = 0; $i < $n; $i++) $pts[] = ['x' => $cx + $r * cos(2 * M_PI * $i / $n), 'y' => $cy + $r * sin(2 * M_PI * $i / $n)];
            return $pts;
        };
        // overlapping fills, tints and ghosts (open and closed), one event running off the frame edge
        $rendered = [
            ['hex' => 'FEF38E', 'tints' => [$circle(90.3, 70.7, 30.0)], 'ghosts' => [['pts' => $circle(95.0, 75.0, 34.0), 'closed' => true]], 'fills' => [$circle(110.0, 80.0, 25.0), $circle(120.5, 90.2, 18.0)]],
            ['hex' => 'B0C4FF', 'tints' => [$circle(150.0, 40.0, 20.0)], 'ghosts' => [['pts' => [['x' => 130.2, 'y' => 20.1], ['x' => 170.6, 'y' => 55.3], ['x' => 190.0, 'y' => 30.0]], 'closed' => false]], 'fills' => []],
            ['hex' => 'FF8F97', 'tints' => [], 'ghosts' => [], 'fills' => [$circle(235.0, 140.0, 30.0)]],
        ];
        $regions = new EventRegionSvg($rendered, 240, 160);
        [$x, $y, $w, $h] = $regions->area();
        $this->assertLessThan(240 * 160, $w * $h, 'the area should be smaller than the frame');

        // the frame as before: the same document covering the whole frame
        $full = preg_replace('~^<svg [^>]*>~', '<svg xmlns="http://www.w3.org/2000/svg" width="240" height="160" viewBox="0 0 240 160">', $regions->toSvg());
        $background = $this->background();
        $before = clone $background;
        $before->compositeImage($this->png($rasterizer->rasterize($full, 240, 160)), Imagick::COMPOSITE_OVER, 0, 0);

        $after = clone $background;
        $after->compositeImage($this->png($rasterizer->rasterize($regions->toSvg(), $w, $h)), Imagick::COMPOSITE_OVER, $x, $y);

        $this->assertSame(0.0, (float) $before->compareImages($after, Imagick::METRIC_ABSOLUTEERRORMETRIC)[1], 'differing pixels');
        $this->assertSame(
            md5(implode(',', $before->exportImagePixels(0, 0, 240, 160, 'RGBA', Imagick::PIXEL_SHORT))),
            md5(implode(',', $after->exportImagePixels(0, 0, 240, 160, 'RGBA', Imagick::PIXEL_SHORT)))
        );
    }

    /**
     * A textured opaque background (random each call: clone it to reuse), with a
     * real alpha channel like a frame's: plasma leaves noise in the unused one.
     */
    private function background(): Imagick
    {
        $im = new Imagick();
        $im->newPseudoImage(240, 160, 'plasma:red-blue');
        $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        $im->setImageFormat('png');
        return $im;
    }

    private function png(string $blob): Imagick
    {
        $im = new Imagick();
        $im->readImageBlob($blob);
        return $im;
    }
}
