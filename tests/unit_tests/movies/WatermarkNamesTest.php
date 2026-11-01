<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Movie\WatermarkNames;

final class WatermarkNamesTest extends TestCase
{
    protected function setUp(): void
    {
        WatermarkNames::clearCache();
    }

    protected function tearDown(): void
    {
        WatermarkNames::clearCache();
    }

    /** A textured opaque background (random each call: clone it to reuse), with a real alpha channel like a frame's. */
    private function background(): Imagick
    {
        $im = new Imagick();
        $im->newPseudoImage(480, 120, 'plasma:red-yellow');
        $im->setImageAlphaChannel(Imagick::ALPHACHANNEL_OPAQUE);
        $im->setImageFormat('png');
        return $im;
    }

    /** drawWatermarkText() before the sprite: names and dates drawn straight onto the image. */
    private function drawDirect(Imagick $im, string $names, string $dates, int $x, int $y): void
    {
        $under = WatermarkNames::outlineDraw();
        $im->annotateImage($under, $x, $y, 0, $names);
        $m = $im->queryFontMetrics($under, $names, true);
        $im->annotateImage($under, $m['textWidth'] + 10 + $x, $y, 0, $dates);
        $text = WatermarkNames::textDraw();
        $im->annotateImage($text, $x, $y, 0, $names);
        $m = $im->queryFontMetrics($text, $names, true);
        $im->annotateImage($text, $m['textWidth'] + 10 + $x, $y, 0, $dates);
    }

    /** drawWatermarkText() now: names from the sprite, dates drawn as before. */
    private function drawWithSprite(Imagick $im, string $names, string $dates, int $x, int $y): void
    {
        WatermarkNames::draw($im, $names, $x, $y);
        $under = WatermarkNames::outlineDraw();
        $m = $im->queryFontMetrics($under, $names, true);
        $im->annotateImage($under, $m['textWidth'] + 10 + $x, $y, 0, $dates);
        $text = WatermarkNames::textDraw();
        $m = $im->queryFontMetrics($text, $names, true);
        $im->annotateImage($text, $m['textWidth'] + 10 + $x, $y, 0, $dates);
    }

    /** Peak per-channel RGB difference in 8-bit levels. */
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

    public static function layersProvider(): array
    {
        return [
            '1 layer'  => ["SDO AIA 304\n", "2026-07-12   17:02:05\n"],
            '2 layers' => ["SDO AIA 171\nSDO AIA 193\n", "2026-07-12   17:02:09\n2026-07-12   17:02:04\n"],
            '3 layers' => ["SOHO LASCO C3 white-light\nSOHO LASCO C2 white-light\nSDO AIA 304\n", "2026-07-12   16:54:07\n2026-07-12   16:48:07\n2026-07-12   17:02:05\n"],
        ];
    }

    /** @dataProvider layersProvider */
    public function testItShouldMatchDrawingTheNamesDirectly(string $names, string $dates): void
    {
        $lines = substr_count($names, "\n");
        $y = 120 - 15 - 10 * $lines;
        $background = $this->background();
        $direct = clone $background;
        $this->drawDirect($direct, $names, $dates, 12, $y);
        $sprites = clone $background;
        $this->drawWithSprite($sprites, $names, $dates, 12, $y);

        $this->assertLessThanOrEqual(1, $this->peakDiff($direct, $sprites), 'watermark differs by more than rounding');
    }

    public function testItShouldRenderTheNamesOnceAndReuseThem(): void
    {
        $im = $this->background();
        foreach (range(0, 9) as $frame) {
            WatermarkNames::draw($im, "SDO AIA 304\n", 12.0, 95.0);
            WatermarkNames::draw($im, "SDO AIA 171\nSDO AIA 193\n", 85.0, 85.0);
        }
        $this->assertSame(2, WatermarkNames::cacheSize());
    }

    public function testItShouldDrawFractionalPositionsDirectly(): void
    {
        $direct = $this->background();
        $drawn = clone $direct;
        $direct->annotateImage(WatermarkNames::outlineDraw(), 12.5, 95.25, 0, "SDO AIA 304\n");
        $direct->annotateImage(WatermarkNames::textDraw(), 12.5, 95.25, 0, "SDO AIA 304\n");
        WatermarkNames::draw($drawn, "SDO AIA 304\n", 12.5, 95.25);

        $this->assertSame(0, WatermarkNames::cacheSize());
        $this->assertSame(0, $this->peakDiff($direct, $drawn));
    }

    public function testItShouldDrawSomething(): void
    {
        $plain = $this->background();
        $drawn = clone $plain;
        WatermarkNames::draw($drawn, "SDO AIA 304\n", 12.0, 95.0);
        $this->assertGreaterThan(100, $this->peakDiff($plain, $drawn));
    }
}
