<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2ImageXMLBox.php';

final class JP2ImageXMLBoxTest extends TestCase
{
    // Tests the case where DSUN is below 0.04AU which is expected
    // to be unrealistic and invalid. There are some older jp2 files with
    // invalid metadata that need to be corrected, this is a temporary
    // workaround for dealing with those files.
    public function test_getDsunWorkaroundBadFile(): void
    {
        $known_bad_file = HV_JP2_DIR . "/HMI/2010/12/06/continuum/2010_12_06__18_56_41_105__SDO_HMI_HMI_continuum.jp2";
        // If the file doesn't exist, don't continue with the test.
        if (!file_exists($known_bad_file)) {
            $this->markTestSkipped("File to test not present on this system");
            return;
        }

        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($known_bad_file);
        $this->assertEquals($xmlBox->getDSun(), HV_CONSTANT_AU);
    }

    public function test_getDsunWorkaroundGoodFile(): void
    {
        $known_good_file = HV_JP2_DIR . "/HMI/2022/08/02/magnetogram/2022_08_02__10_47_53_000__SDO_HMI_HMI_magnetogram.jp2";
        // If the file doesn't exist, don't continue with the test.
        if (!file_exists($known_good_file)) {
            $this->markTestSkipped("File to test not present on this system");
            return;
        }

        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($known_good_file);
        $this->assertNotEquals($xmlBox->getDSun(), HV_CONSTANT_AU);
    }

    public function test_getRsun(): void
    {
        $test_file = dirname(__FILE__) . "/../test_files/test.jp2";
        if (!file_exists($test_file)) {
            $this->markTestSkipped("File to test not present on this system");
            return;
        }

        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($test_file);
        $rsun = $xmlBox->getRSun();
	$this->assertEquals(1626.7751, $rsun);
    }

}
