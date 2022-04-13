<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

include_once "../test_header.php";

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';

final class test_getUsageStatistics extends TestCase
{
    public function testGetUsagetStatistics(): void
    {
        $stats = new Database_Statistics();
        $resolution = "weekly";
        $startDate = "2022-01-01 00:00:00";
        $endDate = "2022-04-13 23:59:59";
        try {
            $result = $stats->getUsageStatistics($resolution, $startDate, $endDate);
            $this->assertTrue (true);
        } catch (Exception $exception) {
            $this->fail("Exception thrown: " . $exception->getMessage());
        }
        // Uncomment if you want to capture the result
        // echo $result;
    }
}
