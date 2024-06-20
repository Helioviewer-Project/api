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
            'layerstring' => '[SDO,AIA,304,1,100,0,60,1,2022-05-19T18:24:31.000Z],[STEREO_A,SECCHI,EUVI,171,2,100,0,60,1,2022-04-11T11:24:40.000Z]'
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

    public function test_ValidateArrayIntegersProblem1(): void
    {
        // The expected layer string to be given
        $input = array(
            'sources' => ''
        );

        $rules = array(
            'array_ints' => array('sources')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for sources. Please specify an integer array value, as ex:1,2,3');
        Validation_InputValidator::checkInput($rules, $input, $input) ;
        // checkInput will raise an exception if it fails, so assertTrue means
        // that no exception was raised.
    }

    public function test_ValidateArrayIntegersProblem2(): void
    {
        // The expected layer string to be given
        $input = array(
            'sources' => 'a,1,2'
        );

        $rules = array(
            'array_ints' => array('sources')
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid value for sources. Please specify an integer array value, as ex:1,2,3');
        Validation_InputValidator::checkInput($rules, $input, $input) ;
        // checkInput will raise an exception if it fails, so assertTrue means
        // that no exception was raised.
    }

    public function test_ValidateArrayIntegersCorrectly(): void
    {
        // The expected layer string to be given
        $input = array(
            'sources' => '4,1,2'
        );

        $rules = array(
            'array_ints' => array('sources')
        );

        Validation_InputValidator::checkInput($rules, $input, $input) ;

        $this->assertEquals($input, [
            'sources' => [4,1,2]
        ]);
        // checkInput will raise an exception if it fails, so assertTrue means
        // that no exception was raised.
    }
}
