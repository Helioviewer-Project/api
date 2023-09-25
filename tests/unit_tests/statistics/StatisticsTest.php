<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';

class StatisticsTestHarness extends Database_Statistics {
    public function __construct() { parent::__construct(); }
    public function GetDevice() { return $this->_GetDevice(); }
}

final class StatisticsTest extends TestCase
{
    public function testGetUsageStatistics(): void
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

    public function testGetDevice_NoUserAgent() {
        $stats = new StatisticsTestHarness();
        $result = $stats->GetDevice();
        $this->assertEquals("UNK", $result);
    }

    /**
     * Get device is used to identify the device via the user agent.
     * Test with a few known user agents.
     */
    public function testGetDevice(): void {
        // Known answer tests
        $kats = array(
            [
                'UserAgent' => "Mozilla/5.0 (iPhone; CPU iPhone OS 14_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0.3 Mobile/15E148 Safari/604.1",
                'ExpectedResult' => 'smartphone'
            ],
            [
                'UserAgent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10.15; rv:102.0) Gecko/20100101 Firefox/102.0",
                'ExpectedResult' => 'desktop'
            ],
            [
                'UserAgent' => "python-requests/2.31.0",
                'ExpectedResult' => 'Python Requests'
            ],
            [
                'UserAgent' => "JHV/SWHV-4.4.2.10777 (x86_64 Mac OS X 13.2.1) Eclipse Adoptium JRE 19.0.2",
                'ExpectedResult' => 'JHelioviewer'
            ],
            [
                'UserAgent' => "Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_5) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.1.1 Safari/605.1.15 (Applebot/0.1; +http://www.apple.com/go/applebot)",
                'ExpectedResult' => 'Applebot'
            ]
        );

        $stats = new StatisticsTestHarness();
        foreach ($kats as $kat) {
            $_SERVER['HTTP_USER_AGENT'] = $kat['UserAgent'];
            $result = $stats->GetDevice();
            $this->assertEquals($kat['ExpectedResult'], $result);
        }
    }
}
