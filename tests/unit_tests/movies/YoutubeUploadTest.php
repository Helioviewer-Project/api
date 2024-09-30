<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

require_once HV_ROOT_DIR.'/../src/Module/Movies.php';

use PHPUnit\Framework\TestCase;

final class YoutubeUploadTest extends TestCase
{
    /**
     * Covers https://github.com/Helioviewer-Project/helioviewer.org/issues/592
     * Youtube Upload failed from check youtube auth due to incorrect use of
     * Google API.
     * @runInSeparateProcess
     */
    public function testCheckYoutubeAuth() {
        $params = array("action" => "checkYoutubeAuth");
        $movies = new Module_Movies($params);
        ob_start();
        $movies->execute();
        $output = ob_get_contents();
        ob_end_clean();
        $this->assertEquals("false", $output);
    }
}

