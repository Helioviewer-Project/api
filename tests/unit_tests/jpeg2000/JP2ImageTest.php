<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2Image.php';

final class JP2ImageTest extends TestCase
{
	// Known answer test to confirm this function
	// detects the known resolution levels in certain files.
    public function test_getMaxReduction(): void
    {
		// file => Clevels
		$answers = array(
		    __DIR__ . '/test_data/solo_L3_eui-hrieuv174-image_20220401T103005920_V01.jp2' => 5,
			__DIR__ . '/test_data/2022_01_01__00_03_52_835__SDO_AIA_AIA_193.jp2' => 8
			);

		foreach ($answers as $file => $reduction) {
			// width, height, and scale are not used in this test.
			$jp2 = new Image_JPEG2000_JP2Image($file, 1024, 1024, 1);
			$result = $jp2->getMaxReduction();
			$this->assertEquals($result, $reduction);
		}
    }
}
