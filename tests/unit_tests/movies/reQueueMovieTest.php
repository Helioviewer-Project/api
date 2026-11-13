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
        $url = HV_LOCAL_TEST_URL . "?action=queueMovie&startTime=2023-12-01T00:00:00Z&endTime=2023-12-01T01:00:00Z&layers=[4,1,100]&imageScale=0.6&events=&eventsLabels=false";
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
     * The queued test movie, built. queueMovie puts a real job on the queue:
     * where a worker is listening (a dev box) it builds the movie, and
     * building it here as well would race it on the same frames folder;
     * where none is (CI stops the movies container) it is built here.
     */
    private function _buildTestMovie($result) {
        $movie = $this->_waitForWorker($result->id);
        if ($movie === null) {
            $movie = new Movie_HelioviewerMovie($result->id);
            $movie->build();
        }
        return $movie;
    }

    /**
     * The movie once a listening worker has finished it (completed or failed),
     * or null when nothing has taken it off the queue within $untaken seconds.
     */
    private function _waitForWorker($id, $untaken = 5, $build = 90) {
        $start = microtime(true);
        while (true) {
            $movie = new Movie_HelioviewerMovie($id);
            if ($movie->status >= Movie_HelioviewerMovie::STATUS_COMPLETED) {
                // The worker's last act after the statuses is to delete the
                // frames folder; a test that removes the movie folder must
                // not cross it.
                $frames = dirname($movie->getFilepath()) . '/frames';
                for ($i = 0; $i < 40 && is_dir($frames); $i++) {
                    usleep(250000);
                }
                return $movie;
            }
            $elapsed = microtime(true) - $start;
            if ($movie->status == Movie_HelioviewerMovie::STATUS_QUEUED && $elapsed > $untaken) {
                return null;
            }
            if ($elapsed > $build) {
                $this->fail("A worker took movie $id but did not finish it within $build s");
            }
            usleep(250000);
        }
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
        $movie = $this->_buildTestMovie($result);
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
        $movie = $this->_buildTestMovie($result);
        $movieId = intval(alphaId($result->id, true, 5, HV_MOVIE_ID_PASS));
        $movieDatabase = new Database_MovieDatabase();
        $formatBefore = $movieDatabase->getMovieFormats($movieId)[0]['id'];
        // Attempt to force requeue the movie
        $params = array("id" => $result->id, "force" => true);
        $api = new Module_Movies($params);
        $api->reQueueMovie();
        // Assert that movie is requeued: a new format row, queued, or, with a
        // worker listening, already taken or built again
        $this->assertNotEquals($formatBefore, $movieDatabase->getMovieFormats($movieId)[0]['id'], "a forced requeue replaces the format row");
        $movie = new Movie_HelioviewerMovie($result->id);
        $this->assertContains((int) $movie->status, [Movie_HelioviewerMovie::STATUS_QUEUED, Movie_HelioviewerMovie::STATUS_PROCESSING, Movie_HelioviewerMovie::STATUS_COMPLETED]);

    }

    /**
     * Test where movie is currently being processed. In this case
     * reQueueMovie should do nothing
     * Helper function so the test can be run with both force = true & false
     */
    public function _testRequeueMovie_MovieProcessing($force) {
        // Queue the test movie
        $result = $this->_queueTestMovie();
        // Build the test movie (required to fill in some metadata in the database)
        $movie = $this->_buildTestMovie($result);
        // Mark the movie as processing instead of complete
        $this->_markMovieAsProcessing($movie);
        // Delete movie from disk so it should look like it is processing
        // Before deleting, assert that the movie is in the cache directory
        $cacheDir = HV_CACHE_DIR . "/movies";
        $movieDir = dirname($movie->getFilepath()) . '/';
        $this->assertTrue(substr($movieDir, 0, strlen($cacheDir)) === $cacheDir);
        system("rm -r \"$movieDir\"");
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
        // A listening worker builds the queued movie; let it finish rather
        // than break the movie under it
        $this->_waitForWorker($result->id);
        // Get the movie details from the database
        $movieDatabase = new Database_MovieDatabase();
        $formats = $movieDatabase->getMovieFormats($movieId);

        $this->assertCount(1, $formats, "one format row: the mp4");
        $this->assertContains((int) $formats[0]['status'], [0, 2], "mp4 should be queued (no worker) or completed (a worker built it)");
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
    }

}

