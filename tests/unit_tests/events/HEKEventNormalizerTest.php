<?php declare(strict_types=1);
/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

// File under test
include_once HV_ROOT_DIR.'/../src/Event/HEKEventNormalizer.php';

final class HEKEventNormalizerTest extends TestCase
{
    public function testNormalizeFRM(): void
    {
        $sample_data_file = __DIR__ . "/test_data/sample_frms_and_events";
        $expected_data_file = __DIR__ . "/test_data/expected_normalized_events";
        $data = unserialize(file_get_contents($sample_data_file));
        $frms = $data['frms'];
        $normalized = Event_HEKEventNormalizer::NormalizeFRMs($frms);
        $expected = unserialize(file_get_contents($expected_data_file));
        $this->assertEquals($expected, $normalized);
    }

    public function testNormalizeEvents(): void {
        $sample_data_file = __DIR__ . "/test_data/sample_frms_and_events";
        $data = unserialize(file_get_contents($sample_data_file));
        $normalized = Event_HEKEventNormalizer::Normalize($data['frms'], $data['events']);
        $this->assertEquals(8, count($normalized[0]['groups'][0]['data']));
    }
}
