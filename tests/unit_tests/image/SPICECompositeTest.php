<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerScreenshot.php';
require_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerMovieFrame.php';
require_once HV_ROOT_DIR.'/../src/Image/ImageType/SPICEImage.php';
require_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2ImageXMLBox.php';
require_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

// Exercise the screenshot constructor and complete compositor without writing
// a database record or using the configured screenshot cache for test outputs.
class WCSScreenshotFixture extends Image_Composite_HelioviewerScreenshot
{
    public static string $output;
    public function build($filepath) { parent::build(self::$output); }
}

final class SPICECompositeTest extends TestCase
{
    private string $dir;
    private const SPICE = __DIR__.'/test_data/solo_L4_spice-n-ras_20210821T070531_67108978-000-DR6_7_0.jp2';
    private const AIA = __DIR__.'/../jpeg2000/test_data/2022_01_01__00_03_52_835__SDO_AIA_AIA_193.jp2';

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir().'/spice_wcs_'.uniqid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') as $file) unlink($file);
        rmdir($this->dir);
    }

    private function render(string $file, Helper_RegionOfInterest $roi, bool $movie, string $name, int $opacity = 100, bool $background = false): Imagick
    {
        $meta = (new Database_ImgIndex())->extractJP2MetaInfo($file);
        $date = $file === self::SPICE ? '2021-08-21 07:05:31' : '2022-01-01 00:03:52';
        $labels = $file === self::SPICE
            ? [['name' => 'Solar Orbiter'], ['name' => 'SPICE'], ['name' => 'LW'], ['name' => '103.19']]
            : [['name' => 'SDO'], ['name' => 'AIA'], ['name' => '193']];
        $layer = ['sourceId' => 1, 'uiLabels' => $labels, 'layeringOrder' => 1,
            'opacity' => $opacity, 'name' => $name];
        // Resolve the fixture relative to HV_JP2_DIR without changing config,
        // symlinking data into it, or reading the image database.
        $root = realpath(HV_JP2_DIR);
        $relative = '/'.str_repeat('../', count(explode('/', trim($root, '/'))))
            .ltrim(dirname(realpath($file)), '/');
        $row = array_merge($meta, $layer, ['filepath' => $relative,
            'filename' => basename($file), 'date' => $date]);
        $rows = [1 => $row];
        $visibleLayers = [$layer];
        if ($background) {
            $aia = (new Database_ImgIndex())->extractJP2MetaInfo(self::AIA);
            $aiaLayer = ['sourceId' => 2, 'uiLabels' => [['name' => 'SDO'], ['name' => 'AIA'], ['name' => '193']],
                'layeringOrder' => 1, 'opacity' => 100, 'name' => 'AIA 193'];
            $aiaPath = '/'.str_repeat('../', count(explode('/', trim($root, '/'))))
                .ltrim(dirname(realpath(self::AIA)), '/');
            $rows[2] = array_merge($aia, $aiaLayer, ['filepath' => $aiaPath,
                'filename' => basename(self::AIA), 'date' => '2022-01-01 00:03:52']);
            array_unshift($visibleLayers, $aiaLayer);
        }
        $db = $this->createMock(Database_ImgIndex::class);
        $db->expects($this->exactly(count($rows)))->method('getClosestData')
            ->with($date, $this->anything())->willReturnCallback(function ($date, $source) use ($rows) {
                return $rows[$source];
            });
        // EUI is hidden: only the visible SPICE layer is sent to composition.
        $layers = new class($visibleLayers) {
            private array $layer;
            public function __construct($layer) { $this->layer = $layer; }
            public function toArray() { return $this->layer; }
            public function toString() { return 'fixture'; }
            public function toHumanReadableString() { return 'fixture'; }
        };
        $options = ['database' => $db, 'watermark' => false, 'compress' => false,
            'interlace' => false, 'movie' => $movie, 'size' => 0,
            'action' => 'downloadScreenshot', 'id' => 'fixture', 'timestamp' => '2021-08-21',
            'observationDate' => $date];
        $output = $this->dir.'/'.$name.'.png';
        $args = [$layers, null, false, ['labels' => '', 'trajectories' => ''],
            false, '', 0, 0, $date, $roi, $options];
        if ($movie) {
            $composite = new Image_Composite_HelioviewerMovieFrame($output, ...$args);
        } else {
            WCSScreenshotFixture::$output = $output;
            $composite = new WCSScreenshotFixture(...$args);
        }
        $this->assertFileExists($output);
        $image = new Imagick($output);
        $this->assertSame((int) round($roi->getPixelWidth()), $image->getImageWidth());
        $this->assertSame((int) round($roi->getPixelHeight()), $image->getImageHeight());
        $this->artifact($image, $name);
        return $image;
    }

    private function artifact(Imagick $image, string $name): void
    {
        $dir = getenv('HV_WCS_ARTIFACTS');
        if ($dir) {
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $image->writeImage($dir.'/'.$name.'.png');
        }
    }

    // Independent forward WCS oracle: evaluate CRVAL + diag(CDELT)*PC*
    // (pixel-CRPIX) directly from the fixture XML, not getRefPixelCoords or
    // database refPixel/scale fields. Control points also check the Y flip.
    private function reference(Helper_RegionOfInterest $roi, int $opacity = 100): Imagick
    {
        $header = new Image_JPEG2000_JP2ImageXMLBox(self::SPICE);
        $x = simplexml_load_string($header->getXMLString());
        $this->assertNotEquals((float) $x->CDELT1, (float) $x->CDELT2);
        $this->assertNotEquals(0.0, (float) $x->PC1_2);
        $this->assertNotEquals(0.0, (float) $x->PC2_1);
        $q = (float) $x->DSUN_OBS / HV_CONSTANT_AU;
        $controls = [];
        foreach ([[0, 0], [192, 0], [0, 611]] as [$u, $v]) {
            $dx = $u - (float) $x->CRPIX1;
            $dy = 611 - $v - (float) $x->CRPIX2;
            $wx = ((float) $x->CRVAL1 + (float) $x->CDELT1 *
                ((float) $x->PC1_1 * $dx + (float) $x->PC1_2 * $dy)) * 3600 * $q;
            $wy = ((float) $x->CRVAL2 + (float) $x->CDELT2 *
                ((float) $x->PC2_1 * $dx + (float) $x->PC2_2 * $dy)) * 3600 * $q;
            array_push($controls, $u, $v, ($wx - $roi->left()) / $roi->imageScale(),
                (-$wy - $roi->top()) / $roi->imageScale());
        }
        $jp2 = new Image_JPEG2000_JP2Image(self::SPICE, 192, 611, 1);
        $jp2->extractRegion($this->dir.'/reference.pgm', ['left' => 0, 'top' => 0, 'right' => 192, 'bottom' => 611]);
        $reference = new Imagick($this->dir.'/reference.pgm');
        @$reference->setImageOpacity($opacity / 100);
        $reference->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
        $reference->setImageBackgroundColor('transparent');
        $reference->setImageArtifact('distort:viewport', sprintf('%dx%d+0+0', $roi->getPixelWidth(), $roi->getPixelHeight()));
        $reference->distortImage(Imagick::DISTORTION_AFFINE, $controls, false);
        $reference->setImagePage(0, 0, 0, 0);
        $reference->setImageBackgroundColor('black');
        $reference->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        return $reference;
    }

    private function assertSamePixels(Imagick $expected, Imagick $actual, float $tolerance = 0.00002): void
    {
        $expected = clone $expected;
        $expected->setImageDepth(8);
        $expected->setImageFormat('png');
        $quantized = new Imagick();
        $quantized->readImageBlob($expected->getImageBlob());
        [, $error] = $quantized->compareImages($actual, Imagick::METRIC_MEANABSOLUTEERROR);
        $this->assertLessThan($tolerance, $error, 'Pixel mean absolute error');
    }

    public function testScreenshotAndMovieFrameMatchForwardWCS(): void
    {
        $roi = new Helper_RegionOfInterest(-500, -400, 400, 400, 1.25);
        $expected = $this->reference($roi);
        $this->artifact($expected, 'spice-reference');
        $screenshot = $this->render(self::SPICE, $roi, false, 'spice-screenshot');
        $movie = $this->render(self::SPICE, $roi, true, 'spice-frame');
        $this->assertSamePixels($expected, $screenshot);
        $this->assertSamePixels($expected, $movie);
        $this->assertSamePixels($screenshot, $movie);
        // Compare the old scalar rendering with exactly the same viewport.
        $m = (new Database_ImgIndex())->extractJP2MetaInfo(self::SPICE);
        $jp2 = new Image_JPEG2000_JP2Image(self::SPICE, 192, 611, $m['scale']);
        $legacy = new Image_ImageType_SPICEImage($jp2, $this->dir.'/legacy.png', $roi, [],
            $m['refPixelX'] - 96, -($m['refPixelY'] - 305.5), []);
        $this->artifact($legacy->getIMagickImage(), 'spice-before');
        [, $error] = $expected->compareImages($legacy->getIMagickImage(), Imagick::METRIC_MEANABSOLUTEERROR);
        $this->assertGreaterThan(0.02, $error, 'The fixture must expose the scalar rendering bug');
    }

    public function testCroppedOffsetViewportAndOpacity(): void
    {
        $roi = new Helper_RegionOfInterest(-250, -30, 150, 270, 0.8);
        $expected = $this->reference($roi, 45);
        $this->artifact($expected, 'spice-crop-reference');
        $this->assertSamePixels($expected, $this->render(self::SPICE, $roi, false, 'spice-crop-screenshot', 45));
        $this->assertSamePixels($expected, $this->render(self::SPICE, $roi, true, 'spice-crop-frame', 45));
    }

    public function testVisibleAIABackgroundUsesAdditiveSPICEBlending(): void
    {
        $roi = new Helper_RegionOfInterest(-500, -400, 400, 400, 1.25);
        $expected = $this->render(self::AIA, $roi, false, 'aia-background');
        $expected->compositeImage($this->reference($roi, 45), Imagick::COMPOSITE_PLUS, 0, 0);
        $this->artifact($expected, 'spice-aia-reference');
        // AIA's legacy screenshot and movie resamplers differ. Compare the
        // screenshot against a screenshot reference, and the frame against
        // the corresponding legacy movie background.
        $this->assertSamePixels($expected,
            $this->render(self::SPICE, $roi, false, 'spice-aia-screenshot', 45, true), 0.002);
        $expected = $this->render(self::AIA, $roi, true, 'aia-background-frame');
        $expected->compositeImage($this->reference($roi, 45), Imagick::COMPOSITE_PLUS, 0, 0);
        $this->assertSamePixels($expected,
            $this->render(self::SPICE, $roi, true, 'spice-aia-frame', 45, true), 0.002);
    }

    public function testViewportOutsideRasterIsBlank(): void
    {
        $roi = new Helper_RegionOfInterest(3000, 3000, 3300, 3300, 1);
        $image = $this->render(self::SPICE, $roi, false, 'spice-outside');
        $black = new Imagick();
        $black->newImage(300, 300, 'black');
        $this->assertSamePixels($black, $image);
    }

    public function testAIAKeepsLegacyGeometryInScreenshotAndFrame(): void
    {
        $roi = new Helper_RegionOfInterest(-1100, -1100, 1100, 1100, 4);
        $m = (new Database_ImgIndex())->extractJP2MetaInfo(self::AIA);
        $jp2 = new Image_JPEG2000_JP2Image(self::AIA, $m['width'], $m['height'], $m['scale']);
        require_once HV_ROOT_DIR.'/../src/Image/ImageType/AIAImage.php';
        $legacy = new Image_ImageType_AIAImage($jp2, $this->dir.'/aia-legacy.png', $roi,
            [['name' => 'SDO'], ['name' => 'AIA'], ['name' => '193']],
            $m['refPixelX'] - $m['width']/2, -($m['refPixelY'] - $m['height']/2),
            ['date' => '2022-01-01 00:03:52', 'compress' => false]);
        $expected = clone $legacy->getIMagickImage();
        $expected->setImageBackgroundColor('black');
        $expected->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $this->artifact($expected, 'aia-screenshot-reference');
        $this->assertSamePixels($expected, $this->render(self::AIA, $roi, false, 'aia-screenshot'));
        // The historical movie path uses scaleImage, unlike screenshots.
        $movieLegacy = new Image_ImageType_AIAImage($jp2, $this->dir.'/aia-legacy-frame.png', $roi,
            [['name' => 'SDO'], ['name' => 'AIA'], ['name' => '193']],
            $m['refPixelX'] - $m['width']/2, -($m['refPixelY'] - $m['height']/2),
            ['date' => '2022-01-01 00:03:52', 'compress' => false, 'movie' => true]);
        $expected = clone $movieLegacy->getIMagickImage();
        $expected->setImageBackgroundColor('black');
        $expected->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $this->artifact($expected, 'aia-frame-reference');
        $this->assertSamePixels($expected, $this->render(self::AIA, $roi, true, 'aia-frame'));
    }
}
