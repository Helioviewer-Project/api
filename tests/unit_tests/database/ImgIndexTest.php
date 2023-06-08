<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
include_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';

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
}

