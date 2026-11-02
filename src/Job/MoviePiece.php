<?php
/**
 * Helioviewer.org Movie Piece Resque Job: a range of one movie's frames.
 *
 * Queued by FastMovie::queuePieces() on the piece queue, one job per range,
 * with the movie's id and the range. The job builds its frames from the
 * movie's plan (FastMovie::buildPiece) and reports to the movie's Pieces
 * record; the piece whose report completes the record queues the end of
 * the movie, a Job_MovieBuilder with stage "finish". A piece that fails
 * reports so, and the end of the movie marks it invalid.
 *
 * @package  Helioviewer
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

include_once HV_ROOT_DIR.'/../src/Movie/FastMovie.php';
include_once HV_ROOT_DIR.'/../src/Movie/Pieces.php';
include_once HV_ROOT_DIR.'/../src/Helper/ErrorHandler.php';

use Helioviewer\Api\Movie\FastMovie;
use Helioviewer\Api\Movie\Pieces;
use Helioviewer\Api\Sentry\Sentry;

class Job_MoviePiece
{
    /** Args passed in from resque: movieId, first, last, piece, pieces, and the movie job's eta, format, counter. */
    public array $args;

    /** Resque job instance which is currently executing the job. */
    public Resque_Job $job;

    /** Redis key for the queue. Automatically set by Resque_Job. */
    public string $queue;

    public function perform()
    {
        $movieId = $this->args['movieId'];
        $first   = (int) $this->args['first'];
        $last    = (int) $this->args['last'];
        error_log(sprintf("[Movie:%s] Worker picked up piece %d/%d, frames %d-%d", $movieId,
            $this->args['piece'], $this->args['pieces'], $first + 1, $last + 1));

        $startMs = microtime(true);
        $ok = true;
        try {
            FastMovie::buildPiece($movieId, $first, $last);
        } catch (Throwable $e) {
            $ok = false;
            Sentry::capture($e);
            error_log(sprintf("[Movie:%s] piece %d/%d failed after %dms: %s", $movieId,
                $this->args['piece'], $this->args['pieces'], (int) round((microtime(true) - $startMs) * 1000), $e->getMessage()));
            if ($e instanceof Exception) {
                logException($e, "Resque_");
            }
        }

        // The last piece to report, done or failed, queues the end of the movie.
        if ((new Pieces($movieId))->report($ok)) {
            Resque::enqueue(FastMovie::pieceQueue(), 'Job_MovieBuilder', [
                'movieId' => $movieId,
                'stage'   => 'finish',
                'format'  => $this->args['format'] ?? 'mp4',
                'eta'     => $this->args['eta'] ?? 0,
                'counter' => $this->args['counter'] ?? false,
            ]);
        }
        error_log(sprintf("[Movie:%s] Worker finished piece %d/%d in %dms%s", $movieId,
            $this->args['piece'], $this->args['pieces'], (int) round((microtime(true) - $startMs) * 1000), $ok ? '' : ' (failed)'));
    }
}
