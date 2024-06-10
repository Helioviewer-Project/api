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
        $known_bad_file = __DIR__ . "/test_data/2010_12_06__18_56_41_105__SDO_HMI_HMI_continuum.jp2";
        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($known_bad_file);
        $this->assertEquals($xmlBox->getDSun(), HV_CONSTANT_AU);
    }

    public function test_getDsunWorkaroundGoodFile(): void
    {
        $known_good_file = __DIR__ . "/test_data/2022_08_02__10_47_53_000__SDO_HMI_HMI_magnetogram.jp2";
        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($known_good_file);
        $this->assertNotEquals($xmlBox->getDSun(), HV_CONSTANT_AU);
    }

    public function test_getRsun(): void
    {
        $test_file = __DIR__ . "/test_data/test.jp2";
        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($test_file);
        $rsun = $xmlBox->getRSun();
	    $this->assertEquals(1626.7751, $rsun);
    }

    public function test_getDSun(): void
    {
        $prefix = HV_ROOT_DIR . "/../install/helioviewer/__test__/__tdata__/";
        $known_answers = [
            $prefix . "2012_07_05__03_25_52_200__RHESSI_RHESSI_Back_Projection_25-50keV.jp2" => 152096710000,
            $prefix . "2024_04_16__11_15_41_129__SDO_AIA_AIA_304.jp2" => 150159030000,
            $prefix . "2024_04_16__13_00_13_908__SOHO_EIT_EIT_171.jp2" => 148557089924.02765,
            $prefix . "iris_1330A_20240415_060002.jp2" => 150094000000
        ];
        foreach ($known_answers as $jp2file => $expected) {
            $parser = new Image_JPEG2000_JP2ImageXMLBox($jp2file);
            $dsun = $parser->getDSun();
            $this->assertEquals($expected, $dsun);
        }
    }
}
