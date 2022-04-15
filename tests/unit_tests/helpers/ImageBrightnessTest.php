<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Helper/ImageBrightness.php';

final class ImageBrightnessTest extends TestCase
{
    public function testOldDates(): void
    {
        $testDate = "2010-06-02 00:00:00";
        $testWavelength = "304";
        $brightnessHelper = new Helper_ImageBrightness($testDate, $testWavelength);
        $this->assertEquals(0.95189210028456201, $brightnessHelper->getBrightness());
    }    
}
