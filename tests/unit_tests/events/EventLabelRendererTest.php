<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventLabelRenderer;

final class EventLabelRendererTest extends TestCase
{
    protected function setUp(): void
    {
        EventLabelRenderer::clearCache();
    }

    protected function tearDown(): void
    {
        EventLabelRenderer::clearCache();
    }

    /** A textured opaque background, so outline and text blending is exercised. */
    private function background(): Imagick
    {
        $im = new Imagick();
        $im->newPseudoImage(320, 200, 'plasma:red-yellow');
        $im->setImageFormat('png');
        return $im;
    }

    /** The drawing the compositor did before sprites: outline then text, per line, straight onto the image. */
    private function drawDirect(Imagick $im, string $label, bool $behind, float $x, float $y): void
    {
        foreach (explode("\n", $label) as $count => $value) {
            $value = str_replace(['u03b1', 'u03b2', 'u03b3', 'u00b1', 'u00b2', '&deg;'], ['α', 'β', 'γ', '±', '²', '°'], $value);
            $text = new ImagickDraw();
            $text->setTextEncoding('utf-8');
            $text->setFont(HV_ROOT_DIR . '/../resources/fonts/DejaVuSans.ttf');
            $text->setFontSize(10);
            $text->setStrokeColor('#000C');
            $text->setStrokeAntialias(true);
            $text->setStrokeWidth(3);
            $text->setStrokeOpacity($behind ? 0.18 : 0.3);
            $im->annotateImage($text, $x, $y + $count * 12, 0, $value);
            $text = new ImagickDraw();
            $text->setTextEncoding('utf-8');
            $text->setFont(HV_ROOT_DIR . '/../resources/fonts/DejaVuSans.ttf');
            $text->setFontSize(10);
            $text->setFillColor('#ffff');
            if ($behind) $text->setFillOpacity(0.6);
            $text->setTextAntialias(true);
            $text->setStrokeWidth(0);
            $im->annotateImage($text, $x, $y + $count * 12, 0, $value);
        }
    }

    /**
     * Peak per-channel RGB difference in 8-bit levels. Only colour is compared:
     * annotateImage switches the image's alpha channel on and compositing a
     * sprite does not, but the background is opaque either way (and movie frames
     * are flattened before they are written).
     */
    private function peakDiff(Imagick $a, Imagick $b): int
    {
        $w = $a->getImageWidth(); $h = $a->getImageHeight();
        $pa = $a->exportImagePixels(0, 0, $w, $h, 'RGB', Imagick::PIXEL_CHAR);
        $pb = $b->exportImagePixels(0, 0, $w, $h, 'RGB', Imagick::PIXEL_CHAR);
        $peak = 0;
        foreach ($pa as $i => $v) {
            $peak = max($peak, abs($v - $pb[$i]));
        }
        return $peak;
    }

    public function testItShouldMatchDrawingTheTextDirectly(): void
    {
        $labels = [['CH 3', false], ["AR 13742\nu03b2u03b3 class", false], ['M1.2 u00b1 0.3', true], ["CME\n1200 km/s\n45&deg;", true]];
        $direct = $this->background();
        $sprites = clone $direct;
        foreach ($labels as $i => [$label, $behind]) {
            foreach ([[20, 30], [150, 90], [300, 195], [-4, 8]] as [$x, $y]) {  // includes labels running off the edges
                $this->drawDirect($direct, $label, $behind, $x + $i, $y + $i);
                EventLabelRenderer::draw($sprites, $label, $behind, (float) ($x + $i), (float) ($y + $i));
            }
        }
        $this->assertLessThanOrEqual(1, $this->peakDiff($direct, $sprites), 'labels differ by more than rounding');
    }

    public function testItShouldRenderEachLabelOnceAndReuseIt(): void
    {
        $im = $this->background();
        foreach (range(0, 9) as $frame) {
            EventLabelRenderer::draw($im, 'CH 3', false, 10.0 + $frame, 20.0);
            EventLabelRenderer::draw($im, 'CH 3', true, 50.0, 60.0 + $frame);   // far side: its own sprite
            EventLabelRenderer::draw($im, "AR 13742\nclass", false, 100.0, 100.0);
        }
        $this->assertSame(3, EventLabelRenderer::cacheSize());
    }

    public function testItShouldDrawFractionalPositionsDirectly(): void
    {
        // follow-viewport movies shift labels by sub-pixel amounts: no sprite, same pixels as before
        $direct = $this->background();
        $drawn = clone $direct;
        $this->drawDirect($direct, 'CH 3', false, 40.37, 50.81);
        EventLabelRenderer::draw($drawn, 'CH 3', false, 40.37, 50.81);

        $this->assertSame(0, EventLabelRenderer::cacheSize());
        $this->assertSame(0, $this->peakDiff($direct, $drawn));
    }

    public function testItShouldDrawSomething(): void
    {
        $plain = $this->background();
        $drawn = clone $plain;
        EventLabelRenderer::draw($drawn, 'CH 3', false, 40.0, 50.0);
        $this->assertGreaterThan(100, $this->peakDiff($plain, $drawn));
    }
}
