<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */


use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Module/WebClient.php';
include_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

final class WebClientTest extends TestCase
{
    /**
     * One of the requests seen in the error log has a diffTime that sends the
     * difference back to year 803 BC. This ends up being sent to MySQL as a negative
     * date string and it can't handle that. A patch was made to clamp the date to
     * a minimum date set in the configuration file. This test verifies that works.
     *
     * @runInSeparateProcess
     */
    public function test_getTile_BadDiffTime(): void
    {
        // The bad layer data that has been observed in the logs
        $params = array(
          "action" => "getTile",
          "id" => "1", // TODO: Get dynamically, any image id will work.
          "imageScale" => "1.21022044",
          "x" => "0",
          "y" => "-1",
          "difference" => "1",
          "diffCount" => "90000000000",
          "diffTime" => "0",
          "baseDiffTime" => "2012-03-10T23:36:32Z"
        );

        // Set up the client
        $client = new Module_WebClient($params);

        // Expect the result to say something like "No data before HV_MINIMUM_DATE"
        $date = new DateTime(HV_MINIMUM_DATE);
        $datestr = $date->format("Y-m-d\TH:i:s.000\Z");
        $this->expectOutputRegex("/No .+ data is available on or before " . $datestr . "/");

        $result = $client->execute();
    }

    /**
     * Verifying that the web client state validates incoming json against
     * the client_state schema.
     * @runInSeparateProcess
     */
    public function test_saveWebClientState_valid() {
        $json_string = file_get_contents(__DIR__ . "/test_data/valid.json");
        if ($json_string === false) {
          throw new Exception("Failed to read test json file.");
        }
        $json = json_decode($json_string, true);
        $params = array(
          'action' => 'saveWebClientState',
          'json' => $json
        );
        // Set up the client
        $client = new Module_WebClient($params);
        $client->execute();
        $this->expectNotToPerformAssertions();
    }

    /**
     * Verifying that the web client state validates incoming json against
     * the client_state schema.
     * @runInSeparateProcess
     */
    public function test_saveWebClientState_invalid() {
        $json_string = file_get_contents(__DIR__ . "/test_data/invalid.json");
        if ($json_string === false) {
          throw new Exception("Failed to read test json file.");
        }
        $json = json_decode($json_string, true);
        $params = array(
          'action' => 'saveWebClientState',
          'json' => $json
        );
        // Set up the client
        $client = new Module_WebClient($params);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("centerY");
        $this->expectExceptionMessage("must match the type: number");
        $client->execute();
    }

    /**
     * Verifying eclipse image is created correctly with png image output
     * @runInSeparateProcess
     */
    public function test_getEclipseImageExpectedAsPNG() {
        $params = array(
          'action' => 'getEclipseImage',
        );
        // Set up the client
        $client = new Module_WebClient($params);

        // Save
        ob_start();
        $client->execute();
        $image_string = ob_get_contents();
        ob_end_clean();

        $finfo = new \finfo(FILEINFO_MIME);
        $this->assertEquals($finfo->buffer($image_string), 'image/png; charset=binary');
    }

    /**
     * These are test cases for the generateImage command, which is used for
     * the downloadImage API.
     * - There was initially a bug where subdisk images like IRIS and RHESSI were
     *   not being rendered properly.
     *
     * The simplest way to generate these test cases is to log the parameters
     * being passed to generateImage when executing the downloadImage endpoint.
     */
    public function generateImageTestCaseProvider() {
      return [
        // These are all sample cases that exercise our *Image classes.
        // At this time HMI, SXT, TRACE, EIT, MDI, and SJI classes are untested due
        // to a lack of sample data in the test environment.
        [ 2048, array(['label' => 'Observatory', 'name' => 'SDO'], ['label' => 'Instrument', 'name' => 'AIA'], ['label' => 'Measurement', 'name' => '94']), "/AIA/2024/12/31/94", "2024_12_31__00_04_47_122__SDO_AIA_AIA_94.jp2", "2024-12-31 00:04:47", "png", 4096, 4096],
        // TODO: Test what happens if I make width 512
        [ 101, array(['label' => 'Observatory', 'name' => 'RHESSI']), "/RHESSI/2018/02/11/VIS_CS", "2018_02_11__01_08_40_800__RHESSI_RHESSI_VIS_CS_25-50keV.jp2", "2018-02-11 01:08:40", "png", 101, 101],
        // Test several times with different scales to verify the scale parameter is working properly.
        [ 1280, array(['label' => 'Observatory', 'name' => 'GOES-R'], ['label' => 'Instrument', 'name' => 'SUVI'], ['label' => 'Measurement', 'name' => '131']), "/SUVI/2024/12/31/131", "2024_12_31__00_02_11__GOES-R_SUVI_SUVI_131.jp2", "2024-12-31 00:02:11", "png", 1280, 1280],
        [ 1280, array(['label' => 'Observatory', 'name' => 'GOES-R'], ['label' => 'Instrument', 'name' => 'SUVI'], ['label' => 'Measurement', 'name' => '131']), "/SUVI/2024/12/31/131", "2024_12_31__00_02_11__GOES-R_SUVI_SUVI_131.jp2", "2024-12-31 00:02:11", "jpg", 1280, 1280],
        [ 1024, array(['label' => 'Observatory', 'name' => 'GOES-R'], ['label' => 'Instrument', 'name' => 'SUVI'], ['label' => 'Measurement', 'name' => '131']), "/SUVI/2024/12/31/131", "2024_12_31__00_02_11__GOES-R_SUVI_SUVI_131.jp2", "2024-12-31 00:02:11", "png", 1280, 1280],
        [ 512, array(['label' => 'Observatory', 'name' => 'GONG'], ['label' => 'Instrument', 'name' => 'GONG'], ['label' => 'Detector', 'name' => 'H-alpha']), "/NSO-GONG/2024/12/31/6562", "2024_12_31__00_00_42__NSO-GONG_GONG_H-alpha_6562.jp2", "2024-12-31 00:00:42", "png", 2048, 2048],
        [ 256, array(['label' => 'Observatory', 'name' => 'GONG'], ['label' => 'Instrument', 'name' => 'GONG'], ['label' => 'Detector', 'name' => 'H-alpha']), "/NSO-GONG/2024/12/31/6562", "2024_12_31__00_00_42__NSO-GONG_GONG_H-alpha_6562.jp2", "2024-12-31 00:00:42", "png", 2048, 2048],
        [ 512, array(['label' => 'Observatory', 'name' => 'SOHO'], ['label' => 'Instrument', 'name' => 'LASCO'], ['label' => 'Detector', 'name' => 'C2']), "/LASCO-C2/2023/12/01/white-light", "2023_12_01__00_48_07_442__SOHO_LASCO_C2_white-light.jp2", "2023-12-01 00:48:07", "png", 1024, 1024],
        [ 1024, array(['label' => 'Observatory', 'name' => 'SOLO'], ['label' => 'Instrument', 'name' => 'EUI'], ['label' => 'Detector', 'name' => 'FSI'], ['label' => 'Measurement', 'name' => '174']), "/FSI/2024/07/02/174", "solo_L3_eui-fsi174-image_20240702T120045260_V01.jp2", "2024-07-02 12:00:45", "png", 3040, 3072],
        [ 256, array(['label' => 'Observatory', 'name' => 'STEREO-A'], ['label' => 'Instrument', 'name' => 'SECCHI'], ['label' => 'Detector', 'name' => 'EUVI'], ['label' => 'Measurement', 'name' => '195']), "/EUVI-A/2024/10/08/195", "2024_10_08__01_57_30_006__STEREO-A_SECCHI_EUVI_195.jp2", "2024-10-08 01:57:30", "png", 2048, 2048],
        [ 256, array(['label' => 'Observatory', 'name' => 'STEREO-A'], ['label' => 'Instrument', 'name' => 'SECCHI'], ['label' => 'Detector', 'name' => 'COR2']), "/COR2-A/2024/10/08/white-light", "2024_10_08__01_53_30_005__STEREO-A_SECCHI_COR2_white-light.jp2", "2024-10-08 01:53:30", "png", 2048, 2048],
        [ 256, array(['label' => 'Observatory', 'name' => 'MLSO'], ['label' => 'Instrument', 'name' => 'COSMO'], ['label' => 'Detector', 'name' => 'KCor']), "/KCor/2024/04/09/735", "2024_04_09__17_49_53__MLSO_KCOR_KCOR_white-light-pB.jp2", "2024-04-09 17:49:53", "png", 1024, 1024],
        [ 256, array(['label' => 'Observatory', 'name' => 'PROBA2'], ['label' => 'Instrument', 'name' => 'SWAP'], ['label' => 'Measurement', 'name' => '174']), "/SWAP/2024/12/31/174", "2024_12_31__00_03_19__PROBA2_SWAP_SWAP_174.jp2", "2024-12-31 00:03:19", "png", 1024, 1024],
        [ 256, array(['label' => 'Observatory', 'name' => 'Hinode'], ['label' => 'Instrument', 'name' => 'XRT'], ['label' => "Filter Wheel 1", 'name' => "Al_poly"], ['label' => "Filter Wheel 2", 'name' => "Open"]), "/XRT/2024/11/07/Al_poly/Open", "2024_11_07__00_56_53_858__HINODE_XRT.jp2", "2024-11-07 00:56:53", "png", 256, 256],
      ];
    }

    /**
     * Testing generateImage which is used for the downloadImage API.
     *
     * This information is returned by Database_ImgIndex::getImageInformation
     * @param array $uiLabels An array of associative arrays containing 'label' and 'name' keys for observatory and instrument
     * @param string $filepath The file path of the image
     * @param string $filename The name of the image file
     * @param string $date The date and time of the image in 'Y-m-d H:i:s' format
     * @param string $extension The desired output file extension (e.g., 'png')
     * @param int $desiredWidth The desired output width for the generated image in pixels.
     * @param int $width The width of the jp2 image in pixels
     * @param int $height The height of the jp2 image in pixels
     * @return void
     * @dataProvider generateImageTestCaseProvider
     */
    public function test_generateImage($expectedWidth, $uiLabels, $filepath, $filename, $date, $extension, $width, $height): void {
        // Test generating the full colorized image
        // echo $filepath . "/" . $filename . "\n";
        $params = [];
        $client = new Module_WebClient($params);
        $img = $client->generateImage($uiLabels, $filepath, $filename, $date, $extension, $expectedWidth, $width, $height);
        $img->save();
        $this->assertFileExists($img->getFilepath());

        // Verify the resulting image has the expected dimensions
        $dimensions = getimagesize($img->getFilepath());
        $this->assertNotFalse($dimensions);
        $this->assertEquals($expectedWidth, $dimensions[0], 'generateImage did not produce the correct image dimensions');
    }
    /**
     * Test cases for downloadImage function
     * @runInSeparateProcess
     * @return void
     */
    public function test_downloadImage(): void {
        // Case 1: Neither width nor scale are set
        $params = ['action' => 'downloadImage', 'id' => '1'];
        $client = new Module_WebClient($params);
        ob_start();
        $client->execute();
        $output = ob_get_clean();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($tempFile, $output);
        $this->assertStringStartsWith('image/png', mime_content_type($tempFile));
        $dimensions = getimagesize($tempFile);
        $this->assertEquals(4096, $dimensions[0], 'Image width should be 4096');
        $this->assertEquals(4096, $dimensions[1], 'Image height should be 4096');
        unlink($tempFile);

        $params = ['action' => 'downloadImage', 'id' => '1', 'width' => 256];
        $client = new Module_WebClient($params);
        ob_start();
        $client->execute();
        $output = ob_get_clean();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($tempFile, $output);
        $this->assertStringStartsWith('image/png', mime_content_type($tempFile));
        $dimensions = getimagesize($tempFile);
        $this->assertEquals(256, $dimensions[0], 'Image width should be 256');
        $this->assertEquals(256, $dimensions[1], 'Image height should be 256');
        unlink($tempFile);

        // Case 3: Scale is set
        $params = ['action' => 'downloadImage', 'id' => '1', 'scale' => 2];
        $client = new Module_WebClient($params);
        ob_start();
        $client->execute();
        $output = ob_get_clean();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($tempFile, $output);
        $this->assertStringStartsWith('image/png', mime_content_type($tempFile));
        $dimensions = getimagesize($tempFile);
        $this->assertEquals(2048, $dimensions[0], 'Image width should be 2048');
        $this->assertEquals(2048, $dimensions[1], 'Image height should be 2048');
        unlink($tempFile);

        // Case 4: Scale is smaller than 1
        $params = ['action' => 'downloadImage', 'id' => '1', 'scale' => 0.5];
        $client = new Module_WebClient($params);
        ob_start();
        $client->execute();
        $output = ob_get_clean();
        $tempFile = tempnam(sys_get_temp_dir(), 'test_image');
        file_put_contents($tempFile, $output);
        $this->assertStringStartsWith('image/png', mime_content_type($tempFile));
        $dimensions = getimagesize($tempFile);
        $this->assertEquals(4096, $dimensions[0], 'Image width should be 4096');
        $this->assertEquals(4096, $dimensions[1], 'Image height should be 4096');
        unlink($tempFile);
    }
}
