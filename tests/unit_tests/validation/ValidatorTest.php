<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
include_once HV_ROOT_DIR.'/../src/Validation/InputValidator.php';

final class ValidatorTest extends TestCase
{
    public function test_ValidateLayerArray_Fail(): void
    {
        // The bad layer data that has been observed in the logs
        $badLayerData = array(
            'layerstring' => "SD"
        );
        $expected = array(
            'layer' => array('layerstring')
        );
        
        $this->expectException(InvalidArgumentException::class);
        Validation_InputValidator::checkInput($expected, $badLayerData, $badLayerData);
    }

    public function test_ValidateLayerArray_SingleLayer(): void
    {
        // The expected layer string to be given
        $goodLayerData = array(
            'layerstring' => '[SDO,AIA,304,1,100,0,60,1,2022-05-19T18:24:31.000Z]'
        );

        $expected = array(
            'layer' => array('layerstring')
        );
        
        Validation_InputValidator::checkInput($expected, $goodLayerData,
            $goodLayerData);
        // checkInput will raise an exception if it fails, so assertTrue means
        // that no exception was raised.
        $this->assertTrue(true);
    }

    public function test_ValidateLayerArray_MultipleLayers(): void
    {
        // The expected layer string to be given
        $goodLayerData = array(
            'layerstring' => '[SDO,AIA,304,1,100,0,60,1,2022-05-19T18:24:31.000Z],[SDO,AIA,304,1,100,0,60,1,2022-05-19T18:24:31.000Z]'
        );

        $expected = array(
            'layer' => array('layerstring')
        );
        
        Validation_InputValidator::checkInput($expected, $goodLayerData,
          $goodLayerData);
        // checkInput will raise an exception if it fails, so assertTrue means
        // that no exception was raised.
        $this->assertTrue(true);
    }
}
