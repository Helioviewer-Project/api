<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Net/Proxy.php';
include_once HV_ROOT_DIR.'/../src/Database/MovieDatabase.php';

final class MovieDatabaseTest extends TestCase
{
    public function testGetForbiddenYoutubeVideo()
    {
        $this->markTestSkipped("This video isn't returning forbidden anymore");

        $db = new Database_MovieDatabase();
        $response = $db->getYoutubeVideo("0X6f5V8V29c");
        $this->assertEquals('Forbidden', $response);
    }
}
