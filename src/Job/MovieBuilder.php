<?php
/**
 * Helioviewer.org Movie Builder Resque Job
 *
 * @package  Helioviewer
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

include_once HV_ROOT_DIR.'/../src/Movie/HelioviewerMovie.php';
include_once HV_ROOT_DIR.'/../src/Helper/ErrorHandler.php';
include_once HV_ROOT_DIR.'/../lib/Redisent/Redisent.php';

use Helioviewer\Api\Sentry\Sentry;

class Job_MovieBuilder
{
    /**
     * Args passed in from resque
     */
    public array $args;

    /**
     * Resque job instance which is currently executing the job.
     */
    public Resque_Job $job;

    /**
     * Redis key for the queue. Automatically set by Resque_Job
     */
    public string $queue;

    public function perform()
    {
        $movieId = $this->args['movieId'];
        $format  = $this->args['format'] ?? 'mp4';
        error_log(sprintf("[Movie:%s] Worker picked up, format=%s", $movieId, $format));

        // Build movie
        $startMs = microtime(true);
        try {
            $movie = new Movie_HelioviewerMovie($movieId);
            $movie->build();
        } catch (Exception $e) {

            Sentry::capture($e);
            $elapsed = (int) round((microtime(true) - $startMs) * 1000);
            error_log(sprintf(
                "[Movie:%s] Worker error after %dms: %s",
                $movieId, $elapsed, $e->getMessage()
            ));
            logException($e, "Resque_");

            // If counter was increased at queue time, decrement
            $this->_updateCounter();

            throw $e;
        }

        $elapsed = (int) round((microtime(true) - $startMs) * 1000);
        error_log(sprintf("[Movie:%s] Worker finished in %dms", $movieId, $elapsed));
        $this->_updateCounter();

        // If the queue is empty and no jobs are being processed, set estimated
        // time counter to zero
        //$numWorking = sizeOf($redis->keys("resque:worker:*".HV_MOVIE_QUEUE));
        //$queueSize  = $redis->llen("resque:queue:".HV_MOVIE_QUEUE);

        //if ($numWorking <= 1 && $queueSize == 0) {
        //    $redis->set('helioviewer:movie_queue_wait', 0);
        //    return;
        //}
    }

    /**
     * Decrements movie wait counter for movie if needed
     */
    private function _updateCounter()
    {
        if (!$this->args['counter']) {
            return;
        }
        # Get current time estimation counter
        $redis = new Redisent(HV_REDIS_HOST, HV_REDIS_PORT);
        $totalWait = (int) $redis->get('helioviewer:movie_queue_wait');
        $redis->decrby('helioviewer:movie_queue_wait', min($totalWait, $this->args['eta']));
    }
}
