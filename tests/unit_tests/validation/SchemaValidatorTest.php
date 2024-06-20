<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
include_once HV_ROOT_DIR.'/../src/Validation/InputValidator.php';

const SHOULD_PASS = true;
const SHOULD_FAIL = false;

// TODO: Test posting non-json

final class SchemaValidatorTest extends TestCase
{
    /**
     * Reads the list of test files from the test data folder
     * and returns test data in the format:
     * [
     *  ["test_file", SHOULD_PASS/SHOULD_FAIL],
     *  ...
     * ]
     *
     * All files in the valid folder should pass validation.
     * All files in the invalid folder should fail validation.
     */
    public static function GetTestData(): array {
        $test_data = [];

        $valid_files =  scandir(__DIR__ . "/test_data/valid");
        foreach ($valid_files as $file) {
            if ($file == "." || $file == "..") {
                continue;
            }
            array_push($test_data, ["valid/$file", SHOULD_PASS]);
        }

        $invalid_files = scandir(__DIR__ . "/test_data/invalid");
        foreach ($invalid_files as $file) {
            if ($file == "." || $file == "..") {
                continue;
            }
            array_push($test_data, ["invalid/$file", SHOULD_FAIL]);
        }
        return $test_data;
    }

    /**
     * @dataProvider GetTestData
     */
    public function test_ValidateJsonSchema(string $json_file, bool $expected_result): void
    {
        $json = json_decode(file_get_contents(__DIR__ . "/test_data/" . $json_file));
        if (is_null($json)) {
            throw new Exception("Failed to parse $json_file as json");
        }
        // Catch any validation error so that we can re-throw it with
        // more debug information (i.e. print which test file failed).
        try {
            $params = [ 'json' => $json ];
            $expected = array(
                'schema' => array('json' => 'https://api.helioviewer.org/schema/client_state.schema.json')
            );
            $optional = [];

            Validation_InputValidator::checkInput($expected, $params, $optional);
            if ($expected_result === SHOULD_FAIL) {
                throw new Exception("Passed on '$json_file' when it should have failed");
            }
        } catch (InvalidArgumentException $e) {
            if ($expected_result === SHOULD_PASS) {
                throw new InvalidArgumentException("Failed on '$json_file' when it should have passed: " . $e->getMessage());
            }
        }
        // Mark this test as "not risky"
        // This test works by throwing an exception on failure.
        // No exceptions means that it passed.
        $this->expectNotToPerformAssertions();
    }
}
