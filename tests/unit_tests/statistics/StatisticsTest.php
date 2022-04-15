<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';

final class StatisticsTest extends TestCase
{
    public function testGetUsagetStatistics(): void
    {
        $stats = new Database_Statistics();
        $resolution = "daily";
        $startDate = "2022-04-13 14:58:43";
        $endDate = "2022-04-14 14:58:43";
        try {
            $result = $stats->getUsageStatistics($resolution, $startDate, $endDate);
            $this->assertTrue (true);
        } catch (Exception $exception) {
            $this->fail("Exception thrown: " . $exception->getMessage());
        }
        // Uncomment if you want to capture the result
        // echo $result;
    }

    public function testSaveRedisStats(): void
    {
        $redis = new Redis();
        $redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);

        $statistics = new Database_Statistics();

        $statistics->saveStatisticsFromRedis($redis);
    }
}
