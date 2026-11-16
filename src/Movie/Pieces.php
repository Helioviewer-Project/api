<?php declare(strict_types=1);
/**
 * The pieces of one movie, in Redis: how many were queued, how many are
 * done or failed, how many frames they have written, and when they last
 * reported. The movie job opens it when it queues the pieces; every piece
 * job reports its frames as it writes them and reports once when it ends;
 * the report that brings done + failed up to the count is told so, exactly
 * once, and that piece queues the end of the movie. The status API reads
 * the frames for the progress and the last report for the stall check.
 *
 * One Redis hash per movie (helioviewer:movie:<publicId>:pieces), on a plain
 * Redisent connection as Job_MovieBuilder's wait counter is; the hash
 * commands are not on Resque's prefixed-command list.
 */
namespace Helioviewer\Api\Movie;

require_once HV_ROOT_DIR . '/../lib/Redisent/Redisent.php';

class Pieces
{
    /** The hash goes away a day after it was last touched. */
    const TTL = 86400;

    private \Redisent $redis;
    private string $key;

    public function __construct(string $publicId)
    {
        $this->redis = new \Redisent(HV_REDIS_HOST, HV_REDIS_PORT);
        $this->key   = 'helioviewer:movie:' . $publicId . ':pieces';
    }

    /** Starts the record: $count pieces are expected to report; $started is when the build began. */
    public function open(int $count, string $started): void
    {
        $this->clear();
        $this->redis->hmset($this->key, 'count', $count, 'done', 0, 'failed', 0, 'frames', 0, 'started', $started, 'updated', time());
        $this->redis->expire($this->key, self::TTL);
    }

    /** Forgets the record, as if it had never been opened: progress() is then all zero. */
    public function clear(): void
    {
        $this->redis->del($this->key);
        $this->redis->del($this->key . ':last');
    }

    /** One more frame written by a piece. */
    public function frameDone(): void
    {
        $this->redis->hincrby($this->key, 'frames', 1);
        $this->redis->hset($this->key, 'updated', time());
    }

    /**
     * One piece ended, well or not.
     *
     * @return bool true for exactly one caller: the piece whose report completed the count.
     */
    public function report(bool $ok): bool
    {
        $this->redis->hincrby($this->key, $ok ? 'done' : 'failed', 1);
        $this->redis->hset($this->key, 'updated', time());

        [$count, $done, $failed] = $this->redis->hmget($this->key, 'count', 'done', 'failed');
        if ($count === null || (int) $done + (int) $failed < (int) $count) {
            return false;
        }
        // Complete: the first to set the flag is the one that ends the movie.
        $last = (bool) $this->redis->setnx($this->key . ':last', 1);
        if ($last) {
            $this->redis->expire($this->key . ':last', self::TTL);
        }
        return $last;
    }

    /**
     * count, done, failed, frames, started ('' when never opened) and
     * updated (unix time of the last report); all zero when never opened.
     *
     * @return array{count: int, done: int, failed: int, frames: int, started: string, updated: int}
     */
    public function progress(): array
    {
        $v = $this->redis->hmget($this->key, 'count', 'done', 'failed', 'frames', 'started', 'updated');
        return ['count' => (int) $v[0], 'done' => (int) $v[1], 'failed' => (int) $v[2],
                'frames' => (int) $v[3], 'started' => (string) $v[4], 'updated' => (int) $v[5]];
    }
}
