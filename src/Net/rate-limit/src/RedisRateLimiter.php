<?php

declare(strict_types=1);

namespace RateLimit;

use RateLimit\Exception\LimitExceeded;
use Redis;

require HV_ROOT_DIR."/../src/Net/rate-limit/src/RateLimiter.php";
require HV_ROOT_DIR."/../src/Net/rate-limit/src/SilentRateLimiter.php";

class RedisRateLimiter implements RateLimiter, SilentRateLimiter
{
    /** @var Redis */
    private $redis;

    /** @var string */
    private $keyPrefix;

    public function __construct(Redis $redis, string $keyPrefix = '')
    {
        $this->redis = $redis;
        $this->keyPrefix = $keyPrefix;
    }

    public function limit(string $identifier, Rate $rate): void
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->getCurrent($key);

        if ($current >= $rate->getOperations()) {
            $this->logLimitExceeded($identifier, $rate);
            throw LimitExceeded::for($identifier, $rate);
        }

        $this->updateCounter($key, $rate->getInterval());
    }

    public function limitSilently(string $identifier, Rate $rate): Status
    {
        $key = $this->key($identifier, $rate->getInterval());

        $current = $this->getCurrent($key);

        if ($current <= $rate->getOperations()) {
            $current = $this->updateCounter($key, $rate->getInterval());
        }

        return Status::from(
            $identifier,
            $current,
            $rate->getOperations(),
            time() + $this->ttl($key)
        );
    }

    private function logLimitExceeded(string $identifier, Rate $rate){
        $requestDateTime = date("c");
        // Some formatting to sql style date like "2020-07-01T00:00:00"
        $dateTimeNoMilis = str_replace("T", " ", explode("+",$requestDateTime)[0]); // strip out miliseconds and "T"
        $dateTimeNearestHour = explode(":",$dateTimeNoMilis)[0] . ":00:00"; // strip out minutes and seconds

        // Make compound key
        $key = HV_RATE_EXCEEDED_PREFIX . "/" . $identifier . "/" . $dateTimeNearestHour;//   $this->key($identifier, $rate->getInterval());
        $current = $this->redis->incr($key);
    }

    private function key(string $identifier, int $interval): string
    {
        return "{$this->keyPrefix}/{$identifier}/$interval";
    }

    private function getCurrent(string $key): int
    {
        return (int) $this->redis->get($key);
    }

    private function updateCounter(string $key, int $interval): int
    {
        $current = $this->redis->incr($key);

        if ($current === 1) {
            $this->redis->expire($key, $interval);
        }

        return $current;
    }

    private function ttl(string $key): int
    {
        return max((int) ceil($this->redis->pttl($key) / 1000), 0);
    }
}
