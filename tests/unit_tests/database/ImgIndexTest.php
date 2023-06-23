<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
include_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';


class ImgIndexHarness extends Database_ImgIndex {
    public function __construct() {
        parent::__construct();
    }

    public function dbConnect() {
        $this->_dbConnect();
    }

    public function forceIndex($sourceId) {
        $this->_dbConnect();
        return $this->_forceIndex($sourceId);
    }

    public function getGroupForSourceId($sourceId) {
        $this->_dbConnect();
        return $this->_getGroupForSourceId($sourceId);
    }
}

final class ImgIndexTest extends TestCase
{
    public function test_getDataRange(): void
    {
        $start = '2022-01-01 00:00:00';
        $end = '2022-07-01 00:00:00';
        $sourceId = 8; // AIA 94
        $imgIndex = new Database_ImgIndex();
        $result = $imgIndex->getDataRange($start, $end, $sourceId);
        $this->assertLessThan(HV_MAX_ROW_LIMIT, count($result));
    }

    public function test_processBadXml(): void {
        $file = __DIR__ . "/test_data/2005_01_11__23_59_32_227__SOHO_MDI_MDI_continuum.jp2";
        $imgIndex = new Database_ImgIndex();
        $meta = $imgIndex->extractJP2MetaInfo($file);
        $this->assertEquals(1024, $meta['width']);
        $this->assertEquals(1024, $meta['height']);
    }

    public function test_getGroupForSourceId(): void {
        $imgIndex = new ImgIndexHarness();
        $expected = [
            10000 => "",
            10001 => "groupOne",
            10002 => "groupTwo",
            10003 => "groupTwo",
            10004 => "groupTwo",
            10005 => "groupTwo",
            10006 => "groupTwo",
            10007 => "groupTwo",
            10008 => "groupThree",
            10009 => "groupThree",
            10010 => "groupThree",
            10011 => "groupThree",
            10012 => "groupThree",
            10013 => "groupThree",
            10014 => ""
        ];

        foreach ($expected as $sourceId => $group) {
            $this->assertEquals($group, $imgIndex->getGroupForSourceId($sourceId));
        }
    }

    public function test_getDatasourceIDsString(): void {
        $imgIndex = new ImgIndexHarness();
        // Left side is expected result, right side is input ID
        $expected = [
            ' sourceId = %d ' => [1, 2, 3, 999, 10000, 10014],
            ' groupOne = %d ' => [10001],
            ' groupTwo = %d ' => [10002, 10003, 10004, 10005, 10006, 10007],
            ' groupThree = %d ' => [10008, 10009, 10010, 10011, 10012, 10013]
        ];
        $imgIndex = new ImgIndexHarness();
        $imgIndex->dbConnect();
        foreach ($expected as $formatString => $idList) {
            foreach ($idList as $sourceId) {
                $expectedResult = sprintf($formatString, $sourceId);
                $this->assertEquals($expectedResult, $imgIndex->getDatasourceIDsString($sourceId));
            }
        }
    }

    public function test_forceIndex(): void {
        $test_data = [
            ' FORCE INDEX (date_group) ' => [10001],
            ' FORCE INDEX (groupTwo) ' => [10002, 10003, 10004, 10005, 10006, 10007],
            ' FORCE INDEX (groupThree) ' => [10008, 10009, 10010, 10011, 10012, 10013],
            '' => [1, 2, 3, 999, 10000, 10014]
        ];

        $imgIndex = new ImgIndexHarness();
        $imgIndex->dbConnect();
        foreach ($test_data as $result => $idList) {
            foreach ($idList as $sourceId) {
                $this->assertEquals($result, $imgIndex->forceIndex($sourceId));
            }
        }
    }

    public function test_getDatasourceInformationFromNames() {
        $expected_file = __DIR__ . "/test_data/expected_datasource_info";
        $expected = unserialize(file_get_contents($expected_file));
        $imgIndex = new ImgIndexHarness();
        $result = $imgIndex->getDatasourceInformationFromNames([
            "GONG", "GONG", "h-alpha", "6562"
        ]);
        $this->assertEquals($expected, $result);
    }
}

