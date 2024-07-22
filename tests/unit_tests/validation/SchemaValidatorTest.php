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

    private static function BuildTestDataFromFolder(string $directory, bool $expectation, string $schema) {
        $test_data = [];
        $files =  scandir($directory);
        foreach ($files as $file) {
            if ($file == "." || $file == "..") {
                continue;
            }
            array_push($test_data, ["$directory/$file", $expectation, $schema]);
        }
        return $test_data;
    }

    /**
     * Reads the list of test files from the test data folder
     * and returns test data in the format:
     * [
     *  ["test_file", SHOULD_PASS/SHOULD_FAIL, schema_file],
     *  ...
     * ]
     *
     * All files in the valid folder should pass validation.
     * All files in the invalid folder should fail validation.
     */
    public static function GetTestData(): array {
        $test_data = [];

        $test_data = array_merge($test_data, self::BuildTestDataFromFolder(__DIR__ . "/test_data/client_state/valid", SHOULD_PASS, 'https://api.helioviewer.org/schema/client_state.schema.json'));
        $test_data = array_merge($test_data, self::BuildTestDataFromFolder(__DIR__ . "/test_data/client_state/invalid", SHOULD_FAIL, 'https://api.helioviewer.org/schema/client_state.schema.json'));
        $test_data = array_merge($test_data, self::BuildTestDataFromFolder(__DIR__ . "/test_data/post_movie/valid", SHOULD_PASS, 'https://api.helioviewer.org/schema/post_movie.schema.json'));
        $test_data = array_merge($test_data, self::BuildTestDataFromFolder(__DIR__ . "/test_data/post_screenshot/valid", SHOULD_PASS, 'https://api.helioviewer.org/schema/post_screenshot.schema.json'));
        return $test_data;
    }

    /**
     * @dataProvider GetTestData
     */
    public function test_ValidateJsonSchema(string $json_file, bool $expected_result, string $schema): void
    {
        $json = json_decode(file_get_contents($json_file));
        if (is_null($json)) {
            throw new Exception("Failed to parse $json_file as json");
        }
        // Catch any validation error so that we can re-throw it with
        // more debug information (i.e. print which test file failed).
        try {
            $params = [ 'json' => $json ];
            $expected = array(
                'schema' => array('json' => $schema)
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
