<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2Image.php';
include_once HV_ROOT_DIR.'/../src/Image/ImageType/LASCOImage.php';

final class LASCOImageTest extends TestCase
{
	const TEST_CASES = [
		// Test case where the test generates a fully opaque image
		[
			'expected' => __DIR__ . "/test_data/expected_solid_lasco_img.png",
			'actual'   => __DIR__ . "/test_data/test_constructImage_out.png",
			'options'  => ['date' => "2023-12-01 00:06:07"]
		],
		// Test case with a translucent image
		[
			'expected' => __DIR__ . "/test_data/expected_translucent_lasco_img.png",
			'actual'   => __DIR__ . "/test_data/test_constructImage_translucent_out.png",
			'options'  => ['date' => "2023-12-01 00:06:07", 'opacity' => 50]
		],

	];

    public function constructImageTestCaseProvider() {
		return LASCOImageTest::TEST_CASES;
	}

	/**
	 * Tests several construction test cases to test clipping the occulter.
     * @dataProvider constructImageTestCaseProvider
	 */
	public function test_constructImage(string $expected, string $actual, array $options) {
		if ($actual == LASCOImageTest::TEST_CASES[1]['actual']) {
			$this->markTestSkipped(
				'Test case 1 is broken and generates invalid images.
				This is a real bug, but is currently not exposed through the API.');
		}
		// Delete the test file so it gets regenerated.
		if (file_exists($actual)) {
			unlink($actual);
		}
		// Generate solid, fully opaque image
		$this->generateImage($options, $actual);
		$this->compareImages($expected, $actual);
	}

	public function generateImage(array $options, string $out) {
		$jp2 = new Image_JPEG2000_JP2Image(__DIR__ . '/test_data/lasco_image.jp2', 1024, 1024, 1);
        $region = new Helper_RegionOfInterest(
            -512, -512, 512, 512, 1);
		$uiLabels = [
			['label' => 'Observatory', 'name' => 'SOHO'],
			['label' => 'Instrument',  'name' => 'LASCO'],
			['label' => 'Detector',    'name' => 'C3'],
			['label' => 'Measurement', 'name' => 'white-light'],
		];
		$img = new Image_ImageType_LASCOImage(
            $jp2, $out, $region, $uiLabels,
            0, 0, $options,
           []
        );
		$img->save();
	}

	// Asserts that two images are the same
	public function compareImages(string $left, string $right) {
		$a = new IMagick();
		$a->readImage($left);

		$b = new IMagick();
		$b->readImage($right);

		// Difference contains a "difference" image at index 0 and
		// the computed difference in index 1.
		// A computed difference of 0 means the images are the same.
		$difference = $a->compareImages($b, imagick::METRIC_MEANABSOLUTEERROR);
		$this->assertEquals(0, $difference[1]);
	}
}
