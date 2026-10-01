<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\SvgRasterizer;

final class SvgRasterizerTest extends TestCase
{
    private function svg(int $w, int $h): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '">'
             . '<rect width="' . $w . '" height="' . $h . '" fill="red"/></svg>';
    }

    private static function rsvgInstalled(): bool
    {
        return trim((string) @shell_exec('command -v rsvg-convert 2>/dev/null')) !== '';
    }

    public function testUnusableBinaryIsNotAvailableAndRasteriseThrows(): void
    {
        $r = new SvgRasterizer('/nonexistent/rsvg-convert-xyz');
        $this->assertFalse($r->isAvailable());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/not found on PATH/');
        $r->rasterize($this->svg(10, 10), 10, 10);
    }

    public function testValidSvgRasterisesToPng(): void
    {
        if (!self::rsvgInstalled()) { $this->markTestSkipped('rsvg-convert not installed'); }

        $r = new SvgRasterizer();
        $this->assertTrue($r->isAvailable());

        $png = $r->rasterize($this->svg(12, 12), 12, 12);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8)); // PNG magic number
    }

    public function testBrokenSvgThrowsWithExitCodeAndStderr(): void
    {
        if (!self::rsvgInstalled()) { $this->markTestSkipped('rsvg-convert not installed'); }

        $r = new SvgRasterizer();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/rsvg-convert failed.*exit \d/i');
        $r->rasterize('THIS IS NOT SVG <<<', 10, 10);
    }
}
