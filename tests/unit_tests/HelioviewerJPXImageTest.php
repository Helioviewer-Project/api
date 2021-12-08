<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

include "../../src/Image/JPEG2000/HelioviewerJPXImage.php";

final class HelioviewerJPXImageTest extends TestCase
{
    /**
     * A JPX Movie should NOT be cached if the endTime is in the future.
     * Test will request a JPX movie with endDate in the future and verify
     * that the summary reports cache = False.
     * 
     * After verifying cache = False, attempt to generate the same JPX and
     * verify that a new one is generated.
     */
    public function testCacheConditions_noCache(): void
    {
        //  June 1, 2011 12:00:00 AM, so this test will work with the sample data.
        $now = 1306886400;

        // Future test case, end time is next year.
        $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                    13,
                    "2011-05-31 23:59:30", // Date in the past
                    "2011-06-01 00:10:00", // Date in the future
                    60,
                    false,
                    "test.jpx",
                    false,
                    $now);
        
        // Check json to confirm cache = False
        $summary = $this->_parseJsonFile($jpx->getSummaryFile());
        // Wait for jpx to be generated before continuing
        $this->assertTrue($this->_waitForFileGeneration($jpx->getJpxFile(), 5));
        // Assert that cache value is set to False.
        $this->assertFalse($summary->cache);

        // Since cache is set to false, a new request should generate a new jpx.
        // Get the timestamp of the existing file to compare against the new one.
        $original_timestamp = filemtime($jpx->getJpxFile());
        // Request another JPX.
        $jpx_new = new Image_JPEG2000_HelioviewerJPXImage(
                            13,
                            "2011-05-31 23:59:30", // Date in the past
                            "2011-06-01 00:10:00", // Date in the future
                            60,
                            false,
                            "test.jpx",
                            false,
                            $now);
        // Check the new filetime to verify that a new jpx was generated.
        $new_timestamp = filemtime($jpx_new->getJpxFile());
        $this->assertTrue($new_timestamp > $original_timestamp);
        
        // Cleanup the generated test files.
        unlink($jpx->getSummaryFile());
        unlink($jpx->getJpxFile());
    }

    /**
     * A JPX Movie should be cached if the endTime is in the past.
     * Test will request a JPX movie with endDate in the past and verify
     * that the summary reports cache = True.
     * 
     * After verifying cache = True, request the same JPX file and verify
     * that a new jpx is not generated.
     */
    public function testCacheConditions_yesCache(): void
    {
        // Future test case, end time is next year.
        $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                    13,
                    "2011-05-31 23:59:30", // Date in the past
                    "2011-06-01 00:10:00", // Date in the past
                    60,
                    false,
                    "test.jpx");
        
        // Check json to confirm cache = False
        $summary = $this->_parseJsonFile($jpx->getSummaryFile());
        // Wait for jpx to be generated before continuing
        $this->assertTrue($this->_waitForFileGeneration($jpx->getJpxFile(), 5));
        // Assert that cache value is set to True.
        $this->assertTrue($summary->cache);

        // Since cache is set to true, a new request should use the existing jpx file.
        // Get the timestamp of the existing file to compare after the next request.
        $original_timestamp = filemtime($jpx->getJpxFile());
        // Request another JPX.
        $jpx_new = new Image_JPEG2000_HelioviewerJPXImage(
                            13,
                            "2011-05-31 23:59:30", // Date in the past
                            "2011-06-01 00:10:00", // Date in the future
                            60,
                            false,
                            "test.jpx");
        // Check the new filetime to verify that the file has not changed.
        $new_timestamp = filemtime($jpx_new->getJpxFile());
        $this->assertTrue($new_timestamp == $original_timestamp);
        
        // Cleanup the generated test file.
        unlink($jpx->getSummaryFile());
        unlink($jpx->getJpxFile());
    }


    /**
     * Returns a files contents as a string.
     * 
     * @param string $filename File to read
     */
    private function _readFile($filename) {
        $fp = fopen($filename, 'r');
        $contents = fread($fp, filesize($filename));
        fclose($fp);

        return $contents;
    }

    /**
     * Reads a json file from disk and decodes it into a php object.
     * 
     * @param string $filename JSON file to decode
     */
    private function _parseJsonFile($filename) {
        $json_str = $this->_readFile($filename);
        $json_obj = json_decode($json_str);
        return $json_obj;
    }

    /**
     * Waits for a given file to appear on disk.
     * 
     * @param string $filename File name to wait for
     * @param int    $timeout Maximum number of seconds to wait
     * 
     * @returns bool True if file is created, false on timeout.
     */
    private function _waitForFileGeneration($filename, $timeout_s) {
        $seconds_elapsed = 0;
        while (!file_exists($filename)) {
            if ($seconds_elapsed > $timeout_s) {
                // Timed out waiting for file to be created
                break;
            }
            sleep(1);
            $seconds_elapsed += 1;
        }

        $result = file_exists($filename);
        return $result;
    }
}