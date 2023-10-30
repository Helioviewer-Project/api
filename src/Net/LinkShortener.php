<?php declare(strict_types=1);

/**
 * Simple Redis-Based link shortener
 */
class Net_LinkShortener
{
    protected static function GetRedisInstance(): Redis {
        $redis = new Redis();
        $redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);
        $db = defined('HV_SHORTENER_REDIS_DB') ? HV_SHORTENER_REDIS_DB : 10;
        $redis->select($db);
        return $redis;
    }

    protected static function GenerateShortString(string $longUrl): string {
        $hash = substr(md5($longUrl), 0, 16);
        return $hash;
    }

    public static function Get(string $shortLink): ?string {
        $redis = self::GetRedisInstance();
        $value = $redis->get($shortLink);
        if ($value === false) {
            return null;
        } else {
            return $value;
        }
    }

    public static function Create(string $longUrl): string {
        $redis = self::GetRedisInstance();
        $key = self::GenerateShortString($longUrl);
        $redis->set($key, $longUrl);
        return $key;
    }
}
