<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventsStateManager;

// Dependencies
include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';
include_once HV_ROOT_DIR.'/../src/Database/MovieDatabase.php';
include_once HV_ROOT_DIR.'/../src/Movie/HelioviewerMovie.php';

final class HelioviewerMovieTest extends TestCase
{
    const TEST_LAYER = "[SOHO,LASCO,C2,white-light,2,100,0,60,1,2024-07-11T09:03:05.000Z]";

    /**
     * Inserts a test movie into the database and returns its public ID
     */
    static private function InsertSohoTestMovie(): string {
        // Get a handle to the movie database
        $movieDb = new Database_MovieDatabase();
        // Set up arguments needed to insert a movie into the database
        $imageScale = 16;
        $roi = new Helper_RegionOfInterest(-16600, -8000, 16600, 8000, $imageScale);
        $soho_layer = HelioviewerMovieTest::TEST_LAYER;
        $layers = new Helper_HelioviewerLayers($soho_layer);
        $events_manager = EventsStateManager::buildFromEventsState([]);
        // Insert a test movie
        $dbId = $movieDb->insertMovie(
            "2023-12-01 00:00:00",
            "2023-12-01 01:00:00",
            "2023-12-01 00:30:00",
            $imageScale,
            $roi->getPolygonString(),
            10,
            true,
            HelioviewerMovieTest::TEST_LAYER,
            bindec($layers->getBitMask()),
			$events_manager->export(),
            false,
            false,
            false,
            "disabled",
            0,
            0,
            $layers->length(),
            1,
            1,
            5,
            0,
            false,
            [
                "labels" => "",
                "trajectories" => ""
            ]
        );

        $movieDb->insertMovieFormat($dbId, 'mp4');
        // Return the database ID as a public ID.
        return alphaID($dbId, false, 5, HV_MOVIE_ID_PASS);
    }

    public function testBuildMovie() {
        $movie_id = $this->InsertSohoTestMovie();
        $movie_builder = new Movie_HelioviewerMovie($movie_id);
        // When movie is first inserted, it should have a queued status
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_QUEUED, $movie_builder->status);
        // Build the movie
        $movie_builder->build();
        // After the movie is built, status should be completed.
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_COMPLETED, $movie_builder->status);

        // After reloading the movie, status should still be completed.
        $movie_builder = new Movie_HelioviewerMovie($movie_id);
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_COMPLETED, $movie_builder->status);

        return $movie_id;
    }

    /**
     * @depends testBuildMovie
     */
    public function testIsComplete(string $movie_id) {
        $completed = new Movie_HelioviewerMovie($movie_id);
        $this->assertTrue($completed->isComplete());
        $not_completed_id = $this->InsertSohoTestMovie();
        $not_completed = new Movie_HelioviewerMovie($not_completed_id);
        $this->assertFalse($not_completed->isComplete());
    }

    /**
     * @depends testBuildMovie
     */
    public function testGetCompletedMovieInformation(string $movie_id) {
        $today = new DateTime();
        $today_date_path = $today->format('Y/m/d');
        $movie = new Movie_HelioviewerMovie($movie_id);
        // These should align with the generated test movie.
        // See InsertSohoTestMovie
        $checkNonVerboseInfo = function($info, $movie_id) use($today_date_path) {
            $this->assertEquals(1, $info['frameRate']);
            $this->assertEquals(5, $info['numFrames']);
            // These dates line up with the default environment's test data.
            $this->assertEquals("2023-12-01 00:00:07", $info['startDate']);
            $this->assertEquals("2023-12-01 00:48:07", $info['endDate']);
            $this->assertEquals(2076, $info['width']);
            $this->assertEquals(1000, $info['height']);
            $this->assertEquals("SOHO LASCO C2 white-light (2023-12-01 00:00:07 - 00:48:07 UTC)", $info['title']);
            $thumbnails = [
                'icon' => HV_CACHE_URL . "/movies/$today_date_path/$movie_id/preview-icon.png",
                'small' => HV_CACHE_URL . "/movies/$today_date_path/$movie_id/preview-small.png",
                'medium' => HV_CACHE_URL . "/movies/$today_date_path/$movie_id/preview-medium.png",
                'large' => HV_CACHE_URL . "/movies/$today_date_path/$movie_id/preview-large.png",
                'full' => HV_CACHE_URL . "/movies/$today_date_path/$movie_id/preview-full.png",
            ];
            $this->assertEquals($thumbnails, $info['thumbnails']);
            $url = HV_CACHE_URL . "/movies/$today_date_path/$movie_id/2023_12_01_00_00_07_2023_12_01_00_48_07_LASCO_C2.mp4";
            $this->assertEquals($url, $info['url']);
        };
        $info = $movie->getCompletedMovieInformation();
        $checkNonVerboseInfo($info, $movie_id);

        // Test verbose info
        $verboseInfo = $movie->getCompletedMovieInformation(true);
        $checkNonVerboseInfo($verboseInfo, $movie_id);
        // This should contain the time the movie was created
        $timestamp = DateTime::createFromFormat("Y-m-d H:i:s", $verboseInfo['timestamp']);
        // At least compare they have the same date
        $this->assertEquals($today->format('Y-m-d'), $timestamp->format('Y-m-d'));
        $this->assertEquals(5.0, $verboseInfo['duration']);
        $this->assertEquals(16, $verboseInfo['imageScale']);
        $this->assertEquals(HelioviewerMovieTest::TEST_LAYER, $verboseInfo['layers']);
        $this->assertIsArray($verboseInfo['events']);
        $this->assertEquals(-16600, $verboseInfo['x1']);
        $this->assertEquals(-8000, $verboseInfo['y1']);
        $this->assertEquals(16600, $verboseInfo['x2']);
        $this->assertEquals(8000, $verboseInfo['y2']);

        // Test case where the movie has not been built
        $new_id = $this->InsertSohoTestMovie();
        $movie = new Movie_HelioviewerMovie($new_id);
        $this->expectException(MovieNotCompletedException::class);
        $this->expectExceptionMessage($new_id);
        $info = $movie->getCompletedMovieInformation();
    }

    public function testFilePath() {
        $movie_id = $this->InsertSohoTestMovie();
        $movie = new Movie_HelioviewerMovie($movie_id);
        $this->assertStringStartsWith(HV_CACHE_DIR . "/movies", $movie->getFilepath());
        $this->assertStringContainsString($movie_id, $movie->getFilepath());
    }

    /**
     * @depends testBuildMovie
     */
    public function testGetDuration(string $movie_id) {
        // Check the duration of the processed movie.
        $completed_movie = new Movie_HelioviewerMovie($movie_id);
        $this->assertEquals(5.0, $completed_movie->getDuration());

        // New movies that haven't been processed don't have a duration.
        $movie_id = $this->InsertSohoTestMovie();
        $new_movie = new Movie_HelioviewerMovie($movie_id);
        $this->expectException(MovieNotCompletedException::class);
        $this->expectExceptionMessage($movie_id);
        $new_movie->getDuration();
    }

    /**
     * @depends testBuildMovie
     */
    public function testGetTitle(string $movie_id) {
        // Test the expected title for the test movie
        $completed = new Movie_HelioviewerMovie($movie_id);
        $this->assertEquals("SOHO LASCO C2 white-light (2023-12-01 00:00:07 - 00:48:07 UTC)", $completed->getTitle());

        // New movies title is only available after processing, should throw an exception
        // if you attempt to get the title before its ready.
        $movie_id = $this->InsertSohoTestMovie();
        $new_movie = new Movie_HelioviewerMovie($movie_id);
        $this->expectException(MovieNotCompletedException::class);
        $this->expectExceptionMessage($movie_id);
        $new_movie->getTitle();
    }
}
