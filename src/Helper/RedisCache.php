<?php declare(strict_types=1);

require_once HV_ROOT_DIR . "/../vendor/autoload.php";

class RedisCache implements DeviceDetector\Cache\CacheInterface {
    // Redis Docs: https://phpredis.github.io/phpredis/Redis.html
    private $_redis;

    /**
     * Create a redis-based cache instance
     * @param string $host Redis server hostname/ip
     * @param int $port Redis server port
     */
    public function __construct(string $host = "", int $port = 0) {
        if ($host == "") { $host = HV_REDIS_HOST; }
        if ($port == 0) { $port = HV_REDIS_PORT; }
        $this->_redis = new Redis();
        $this->_redis->connect($host, $port);
    }

    /**
     * Return cached value for the given id
     * @param string $id
     *
     * @return mixed
     */
    public function fetch(string $id): mixed {
        $value = $this->_redis->get($id);
        if ($value !== false) {
            return unserialize($value);
        }
        return false;
    }

    /**
     * Returns if a given cache key exists
     * @param string $id
     *
     * @return bool
     */
    public function contains(string $id): bool {
        return $this->_redis->exists($id) > 0;
    }

    /**
     * Add an entry to the cache with an expiration date
     * @param string $id
     * @param mixed  $data
     * @param int    $lifeTime
     *
     * @return bool
     */
    public function save(string $id, mixed $data, int $lifeTime = 0): bool {
        $options = [];
        // Set expiration date in options only if lifetime is > 0
        if ($lifeTime > 0) {
            $options['EX'] = $lifeTime;
        }
        return $this->_redis->set($id, serialize($data), $options);
    }

    /**
     * Delete an entry from the cache
     * @param string $id
     *
     * @return bool
     */
    public function delete(string $id): bool {
        return $this->_redis->del($id) > 0;
    }

    /**
     * Flush all cache entries
     * @return bool
     */
    public function flushAll(): bool {
        error_log("Warning: flushAll called on RedisCache instance, but it is unimplemented.");
        return true;
    }
}