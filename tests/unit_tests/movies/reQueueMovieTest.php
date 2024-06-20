<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// Dependencies
require_once HV_ROOT_DIR . '/../src/Database/ImgIndex.php';
include_once HV_ROOT_DIR.'/../src/Movie/HelioviewerMovie.php';
// File under test
include_once HV_ROOT_DIR.'/../src/Module/Movies.php';

/**
 * HelioviewerMovie stub class used to call the abort function to simulate a build failure.
 */
class HelioviewerMovie_BuildBreaker extends Movie_HelioviewerMovie {
    public function __construct($id) {
        parent::__construct($id, 'mp4');
    }

    public function abort() {
        $this->_abort("Forced Failure");
    }
}

final class reQueueMovieTest extends TestCase
{
    /**
     * Queues a movie to be used for testing
     * Returns a json decoded result of the API response.
     */
    private function _queueTestMovie() {
        // Queue the movie
        $url = HV_WEB_ROOT_URL . "?action=queueMovie&startTime=2023-12-01T00:00:00Z&endTime=2023-12-01T01:00:00Z&layers=[4,1,100]&imageScale=0.6&events=&eventsLabels=false";
        $result = file_get_contents($url);
        $data = json_decode($result);
        // Confirm the API request was accepted
        if ($data == false) {
            error_log("Error processing API result");
            error_log(print_r($result, true));
            $this->fail("Unable to queue test movie, API returned unexpected result");
        }
        // Check for errors
        if (property_exists($data, "error")) {
            $this->fail("Unable to queue test movie, API returned an error: " . $data->error);
        }
        // If all is good, the result should have an id
        if (!property_exists($data, "id")) {
            $this->fail("Unable to queue test movie, no ID in api result.");
        }
        return $data;
    }

    /**
     * Marks the movie as processing in the database and invalidates the cache
     */
    private function _markMovieAsProcessing($movie) {
        $db = new Database_ImgIndex();
        $success = $db->markMovieAsProcessing($movie->id, "mp4");
        if (!$success) {
            $this->fail("Failed to mark movie as processing");
        }
        $movie->DeleteFromCache($movie->publicId);
    }

    /**
     * reQueueMovie test where movie already exists.
     * Should result in an error saying it will not requeue since
     * the movie is already there
     */
    public function testRequeueMovie_MovieExists() {
        // Queue the test movie
        $result = $this->_queueTestMovie();
        // Build the test movie
        $movie = new Movie_HelioviewerMovie($result->id);
        $movie->build();
        // Attempt to requeue the movie and expect a failure
        $params = array("id" => $result->id);
        $api = new Module_Movies($params);
        $this->expectExceptionMessage("Movie file already exists");
        $api->reQueueMovie();
        // Assert that nothing happens because the movie exists
        $movie = new Movie_HelioviewerMovie($result->id);
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_COMPLETED, $movie->status);
    }

    /**
     * Test where movie already exists, but force=true so movie
     * should be requeued anyway.
     *
     * @runInSeparateProcess
     */
    public function testRequeueMovie_Force() {
        // Queue the test movie
        $result = $this->_queueTestMovie();
        // Build the test movie
        $movie = new Movie_HelioviewerMovie($result->id);
        $movie->build();
        // Attempt to force requeue the movie
        $params = array("id" => $result->id, "force" => true);
        $api = new Module_Movies($params);
        $api->reQueueMovie();
        // Assert that movie is requeued
        $movie = new Movie_HelioviewerMovie($result->id);
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_QUEUED, $movie->status);

    }

    /**
     * Test where movie is currently being processed. In this case
     * reQueueMovie should do nothing
     * Helper function so the test can be run with both force = true & false
     */
    public function _testRequeueMovie_MovieProcessing($force) {
        // Queue the test movie
        $result = $this->_queueTestMovie();
        // Build the test movie
        $movie = new Movie_HelioviewerMovie($result->id);
        // build required to fill in some metadata in the database
        $movie->build();
        // Mark the movie as processing instead of complete
        $this->_markMovieAsProcessing($movie);
        // Delete movie from disk so it should look like it is processing
        // Before deleting, assert that the movie is in the cache directory
        $cacheDir = HV_CACHE_DIR . "/movies";
        $this->assertTrue(substr($movie->directory, 0, strlen($cacheDir)) === $cacheDir);
        system("rm -r \"$movie->directory\"");
        // Attempt to requeue the movie
        $params = array("id" => $result->id, "force" => $force);
        $api = new Module_Movies($params);
        // Expect an error saying the movie is being processed
        $this->expectExceptionMessage("Movie is currently being processed");
        $api->reQueueMovie();
        // Assert that movie is still processing and not queued
        $movie = new Movie_HelioviewerMovie($result->id);
        $this->assertEquals(Movie_HelioviewerMovie::STATUS_PROCESSING, $movie->status);
    }

    /**
     * Test where movie is currently being processed. In this case
     * reQueueMovie should do nothing (uses force=true)
     * @runInSeparateProcess
     */
    public function testRequeueMovie_MovieProcessing() {
        $this->_testRequeueMovie_MovieProcessing(false);
    }

    /**
     * Test where movie is currently being processed. In this case
     * reQueueMovie should do nothing (uses force=true)
     * @runInSeparateProcess
     */
    public function testRequeueMovie_MovieProcessing_Force() {
        $this->_testRequeueMovie_MovieProcessing(true);
    }

    /**
     * Issue #262 - When mp4 creation fails, movie can't be requeued
     */
    public function testRequeueMovie_262() {
        // Queue the test movie
        $result = $this->_queueTestMovie();
        // Get the movie ID as an integer
        $movieId = alphaId($result->id, true, 5, HV_MOVIE_ID_PASS);
        $movieId = intval($movieId);
        // Get the movie details from the database
        $movieDatabase = new Database_MovieDatabase();
        $formats = $movieDatabase->getMovieFormats($movieId);

        $this->assertEquals($formats[0]['status'], 0, "mp4 should have status of 0 to indicate it was queued");
        $this->assertEquals($formats[1]['status'], 0, "webm should have status of 0 to indicate it was queued");
        // Create instance of class to break the movie.
        $builder = new HelioviewerMovie_BuildBreaker($result->id);
        // Call abort to simulate move build failure.
        try {
            $builder->abort();
        } catch (Exception $e) {
            // Thrown exception is expected, ignore it and move on with the test.
        }

        $formats = $movieDatabase->getMovieFormats($movieId);
        $this->assertEquals($formats[0]['status'], 3, "mp4 should have status of 3 to indicate an error");
        $this->assertEquals($formats[1]['status'], 3, "webm should have status of 3 to indicate an error");
    }

}

