<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Movie\FastMovie;

/**
 * FastMovie::colorTableFor(): the brightness correction folded into the
 * colour table gives the same pixels as the correction and the table applied
 * one after the other to every pixel, for every colour table and the
 * corrections AIA 304 gets over the mission.
 */
final class FastMovieColorTableTest extends TestCase
{
    protected function setUp(): void
    {
        FastMovie::clearColorTables();
    }

    protected function tearDown(): void
    {
        FastMovie::clearColorTables();
    }

    /** The decoded image as decodeLayer has it: a PGM holding every gray level, as PNG at depth 8. */
    private function allGrayLevels(): Imagick
    {
        $pixels = '';
        for ($row = 0; $row < 4; $row++) {
            for ($level = 0; $level < 256; $level++) {
                $pixels .= chr($level);
            }
        }
        $im = new Imagick();
        $im->readImageBlob("P5\n256 4\n255\n" . $pixels);
        $im->setImageFormat('PNG');
        $im->setImageDepth(8);
        $im->setImageCompressionQuality(10);
        return $im;
    }

    /** What decodeLayer did before the tables were folded: each step on every pixel. */
    private function applyStepByStep(Imagick $im, string $colorTable, ?float $brightness): void
    {
        if ($brightness !== null) {
            $gammaScale = (($brightness - 1) / 2) + 1;
            $im->levelImage(0, $gammaScale, 65535);
            $im->modulateImage($brightness * 100, 100, 100);
        }
        $im->clutImage(new Imagick($colorTable));
    }

    private function pixels(Imagick $im): array
    {
        return $im->exportImagePixels(0, 0, $im->getImageWidth(), $im->getImageHeight(), 'RGBA', Imagick::PIXEL_SHORT);
    }

    public static function brightnessProvider(): array
    {
        return [
            'no correction'       => [null],
            'neutral (other AIA)' => [1.0],
            'slight'              => [1.07],
            'typical'             => [1.37],
            'strong'              => [2.15],
            'extreme'             => [4.8],
        ];
    }

    /** @dataProvider brightnessProvider */
    public function testItShouldMatchApplyingTheStepsToEveryPixel(?float $brightness): void
    {
        $tables = glob(HV_ROOT_DIR . '/resources/images/color-tables/*.png');
        $this->assertNotEmpty($tables);
        foreach ($tables as $colorTable) {
            $expected = $this->allGrayLevels();
            $this->applyStepByStep($expected, $colorTable, $brightness);

            $folded = $this->allGrayLevels();
            $folded->clutImage(FastMovie::colorTableFor($colorTable, $brightness));

            $this->assertSame($this->pixels($expected), $this->pixels($folded), basename($colorTable));
        }
    }

    public function testItShouldBuildEachTableOnceAndReuseIt(): void
    {
        $table = HV_ROOT_DIR . '/resources/images/color-tables/SDO_AIA_304.png';
        $first = FastMovie::colorTableFor($table, 1.37);
        $this->assertSame($first, FastMovie::colorTableFor($table, 1.37));
        $this->assertNotSame($first, FastMovie::colorTableFor($table, 1.38));
        $this->assertNotSame($first, FastMovie::colorTableFor($table, null));
    }

    public function testItShouldChangeTheColoursWhenBrightnessIsSet(): void
    {
        $table = HV_ROOT_DIR . '/resources/images/color-tables/SDO_AIA_304.png';
        $plain = $this->allGrayLevels();
        $plain->clutImage(FastMovie::colorTableFor($table, null));
        $brighter = $this->allGrayLevels();
        $brighter->clutImage(FastMovie::colorTableFor($table, 1.37));
        $this->assertNotSame($this->pixels($plain), $this->pixels($brighter));
    }
}
