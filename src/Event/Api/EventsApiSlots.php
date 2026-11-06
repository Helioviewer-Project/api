<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Api;

/**
 * A cap on the events requests in flight from this box, held in Redis so
 * that every worker shares it (events_api_max_in_flight in Config.ini).
 *
 * The events server answers at its own pace and the proxy in front of it
 * cuts a request off after 60 s. With 60 pieces asking at once (two
 * 300-frame movies) a quarter of the requests waited past the cut and
 * failed, each then retried for another 60 s, and a piece can end up
 * drawing its frames without events (measured 2026-11-04). With the cap a
 * piece waits on this side, with no clock running, until a slot is free.
 * The movie takes the same time either way: the server's pace is the limit.
 *
 * A slot is a Redis key set only when absent, with a life a little longer
 * than the request timeout, so a worker killed mid-request frees its slot
 * by expiry. Redis trouble never stops a request: when a slot cannot be
 * taken the request goes without one, said once in the log.
 */
class EventsApiSlots
{
    // The Redis keys are DEFAULT_KEY followed by the slot number, 0 to limit - 1:
    // "helioviewer:events:slot:0" ... "helioviewer:events:slot:15" with a cap of 16.
    // Every worker of the box, and every box on the same Redis, sees the same keys.
    public const DEFAULT_KEY = 'helioviewer:events:slot:';
    private const POLL_US    = 100000;   // 0.1 s between tries while every slot is taken
    private const GIVE_UP_S  = 600;      // after this long waiting, go without a slot (and say so)

    private int $limit;                  // how many slots exist, i.e. requests in flight at once
    private int $ttl;                    // seconds a taken slot lives in Redis if nobody releases it
    private string $key;                 // the key prefix (tests use their own, so they never touch the real slots)
    private array $held = [];            // slot => token, the slots this process holds right now
    private ?\Redisent $redis = null;    // the connection, opened on first use
    private bool $broken = false;        // set once Redis failed: from then on every take returns null at once

    /**
     * @param int    $limit Slots in all, at least 1 (0 means "no cap" and is handled by fromConfig)
     * @param int    $ttl   Seconds a slot lives if its holder never releases it (a killed worker)
     * @param string $key   Redis key prefix; the tests pass a random one of their own
     */
    public function __construct(int $limit, int $ttl, string $key = self::DEFAULT_KEY)
    {
        $this->limit = max(1, $limit);
        $this->ttl   = max(1, $ttl);
        $this->key   = $key;
    }

    /**
     * The slots of this box per Config.ini: null (no cap) when
     * events_api_max_in_flight is missing or 0, or there is no Redis.
     */
    public static function fromConfig(): ?self
    {
        $limit = defined('HV_EVENTS_API_MAX_IN_FLIGHT') ? (int) HV_EVENTS_API_MAX_IN_FLIGHT : 0;
        if ($limit < 1 || !defined('HV_REDIS_HOST')) {
            return null;
        }
        // A slot must outlive the request it covers, or a slow request could
        // see its slot expire and be taken by another worker while it is still
        // in flight: the request timeout plus a margin for the response handling.
        $timeout = defined('HV_EVENTS_API_TIMEOUT') ? (float) HV_EVENTS_API_TIMEOUT : 10;
        return new self($limit, (int) ceil($timeout) + 20);
    }

    /** How many requests the box may have in flight at once. */
    public function limit(): int
    {
        return $this->limit;
    }

    /** The slots this process holds. */
    public function held(): array
    {
        return array_keys($this->held);
    }

    /**
     * Waits for a free slot and takes it. Null when Redis is unusable or
     * GIVE_UP_S passed: the caller goes on without a slot.
     *
     * The wait is a poll: try, sleep POLL_US, try again. There is no queue
     * and no order among the waiters; whoever polls first after a release
     * gets the slot. That is enough here, since every waiter is a piece of
     * some movie and they all want the same thing.
     *
     * @param string $logLabel The movie's log tag, so a long wait is logged against it ('' = log nothing)
     */
    public function take(string $logLabel = ''): ?int
    {
        $start = microtime(true);
        while (true) {
            $slot = $this->tryTake($logLabel);
            $waited = microtime(true) - $start;
            if ($slot !== null || $this->broken) {
                // Got one, or Redis just failed (then null, and the caller goes without).
                // A wait above a second is worth a line: it says the box is at the cap.
                if ($waited > 1 && $logLabel !== '') {
                    error_log(sprintf('[%s] waited %.1fs for an events slot (%d in flight allowed on this box)', $logLabel, $waited, $this->limit));
                }
                return $slot;
            }
            if ($waited > self::GIVE_UP_S) {
                error_log(sprintf('[%s] no events slot in %ds (%d in flight allowed on this box), going without one',
                    $logLabel === '' ? 'EventsApi' : $logLabel, self::GIVE_UP_S, $this->limit));
                return null;
            }
            usleep(self::POLL_US);
        }
    }

    /**
     * Takes a free slot now, without waiting; null when all are taken (or
     * Redis is unusable).
     *
     * Each slot is one Redis key. "SET key token NX EX ttl" writes the key
     * only if it does not exist (NX) and gives it a life of ttl seconds (EX),
     * in one atomic step, so two workers trying the same slot at the same
     * moment cannot both win: Redis answers OK to one and nothing to the other.
     * The token is a random value of ours, so that release() can check the
     * slot is still the one we took (see there).
     */
    public function tryTake(string $logLabel = ''): ?int
    {
        if ($this->broken) {
            return null;
        }
        try {
            $redis = $this->redis();
            $token = bin2hex(random_bytes(8));
            // Start at a random slot rather than always at 0, so 60 workers
            // arriving together do not all collide on slot 0, then 1, ...
            $first = random_int(0, $this->limit - 1);
            for ($k = 0; $k < $this->limit; $k++) {
                $slot = ($first + $k) % $this->limit;
                if (isset($this->held[$slot])) {
                    continue;                                   // ours already (the parallel path takes several)
                }
                if ($redis->set($this->key . $slot, $token, 'NX', 'EX', $this->ttl) === 'OK') {
                    $this->held[$slot] = $token;
                    return $slot;
                }
            }
            return null;                                        // every slot is somebody's right now
        } catch (\Throwable $e) {
            // Redis down, unreachable, or refusing: the cap is a protection,
            // not a requirement, so the requests go on uncapped from here on
            // in this process, and the log says so once.
            $this->broken = true;
            error_log(sprintf('[%s] events slots unavailable, the requests go without a cap: %s',
                $logLabel === '' ? 'EventsApi' : $logLabel, $e->getMessage()));
            return null;
        }
    }

    /**
     * Gives a slot back, if it is still ours.
     *
     * A plain DEL would be wrong: if our request ran past the ttl, the key
     * expired and another worker may have taken the same slot with its own
     * token. Deleting it then would free a slot that is in use, and the box
     * would be one over the cap. So the delete runs as a Lua script in Redis,
     * "delete only if the value is still my token", which Redis executes
     * atomically: nobody can take the slot between the check and the delete.
     *
     * @param int|null $slot The slot number take() returned; null (no slot taken) is a no-op
     */
    public function release(?int $slot): void
    {
        if ($slot === null || !isset($this->held[$slot])) {
            return;
        }
        $token = $this->held[$slot];
        unset($this->held[$slot]);
        if ($this->broken) {
            return;                      // Redis is gone; the key, if it exists, expires by itself
        }
        try {
            $this->redis()->eval(
                "if redis.call('get', KEYS[1]) == ARGV[1] then return redis.call('del', KEYS[1]) end return 0",
                1, $this->key . $slot, $token
            );
        } catch (\Throwable $e) {
            // The slot expires on its own.
        }
    }

    /** Gives back every slot this process holds (the parallel path takes several at once). */
    public function releaseAll(): void
    {
        foreach (array_keys($this->held) as $slot) {
            $this->release($slot);
        }
    }

    /**
     * The Redis connection, opened on first use. Redisent is the small client
     * the rate limiter and the movie pieces already use (lib/Redisent); it is
     * not autoloaded, hence the require. The connection is per process: a
     * Resque child gets its own, as it should, since a forked socket shared
     * with the parent would mix the replies.
     */
    private function redis(): \Redisent
    {
        if ($this->redis === null) {
            if (!class_exists('\Redisent', false)) {
                require_once HV_ROOT_DIR . '/../lib/Redisent/Redisent.php';
            }
            $this->redis = new \Redisent(HV_REDIS_HOST, HV_REDIS_PORT);
        }
        return $this->redis;
    }
}
