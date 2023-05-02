<?php declare(strict_types=1);
use PHPUnit\Framework\TestCase;

include_once HV_ROOT_DIR . "/../src/Net/LinkShortener.php";

class LinkShortenerExposed extends Net_LinkShortener {
    public static function GetRedisInstance(): Redis {
        return Net_LinkShortener::GetRedisInstance();
    }
}

final class LinkShortenerTest extends TestCase
{
    protected function setUp(): void
    {
        $redis = LinkShortenerExposed::GetRedisInstance();
        $redis->flushdb();
    }

    public function testDb(): void {
        $redis = LinkShortenerExposed::GetRedisInstance();
        $this->assertEquals(20, $redis->getDBnum());
    }

    public function testCreateLink(): void
    {
        $key = Net_LinkShortener::Create("hello, world");
        $redis = LinkShortenerExposed::GetRedisInstance();
        $this->assertEquals(1, $redis->exists($key));
        $value = $redis->get($key);
        $this->assertEquals($value, Net_LinkShortener::Get($key));
    }
}
