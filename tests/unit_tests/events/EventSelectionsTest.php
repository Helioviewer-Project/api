<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventSelections;

final class EventSelectionsTest extends TestCase
{
    public static function legacyStringProvider(): array
    {
        return [
            'all_frms_hek' => [
                '[AR,all,1]',
                ['HEK>>Active Region'],
            ],
            'specific_frm' => [
                '[FL,NOAA_SWPC,1]',
                ['HEK>>Flare>>NOAA_SWPC'],
            ],
            'semicolon_separated_frms' => [
                '[FL,NOAA_SWPC;SPoCA,1]',
                ['HEK>>Flare>>NOAA_SWPC', 'HEK>>Flare>>SPoCA'],
            ],
            'ccmc_source' => [
                '[C3,all,1]',
                ['CCMC>>DONKI'],
            ],
            'rhessi_source' => [
                '[F2,all,1]',
                ['RHESSI>>Solar Flares'],
            ],
            'multiple_groups' => [
                '[AR,all,1],[FL,all,1]',
                ['HEK>>Active Region', 'HEK>>Flare'],
            ],
            'cross_source' => [
                '[AR,all,1],[C3,all,1]',
                ['HEK>>Active Region', 'CCMC>>DONKI'],
            ],
            'unknown_event_type_skipped' => [
                '[XX,all,1]',
                [],
            ],
            'empty_frms_treated_as_all' => [
                '[AR,,1]',
                ['HEK>>Active Region'],
            ],
            'empty_string' => [
                '',
                [],
            ],
            'only_two_pieces_skipped' => [
                '[AR,all]',
                [],
            ],
            'unknown_in_middle_skipped' => [
                '[AR,all,1],[XX,all,1],[FL,all,1]',
                ['HEK>>Active Region', 'HEK>>Flare'],
            ],
            'empty_brackets' => [
                '[]',
                [],
            ],
            'multiple_empty_brackets' => [
                '[],[]',
                [],
            ],
            'empty_bracket_with_valid' => [
                '[],[AR,all,1]',
                ['HEK>>Active Region'],
            ],
            'too_many_commas' => [
                '[,,,]',
                [],
            ],
            'valid_mixed_with_empty_brackets' => [
                '[AR,all,1],[],[]',
                ['HEK>>Active Region'],
            ],
        ];
    }

    /**
     * @dataProvider legacyStringProvider
     */
    public function testItShouldBuildCorrectSelectionsFromLegacyString(string $input, array $expected): void
    {
        $selections = EventSelections::buildFromLegacyEventStrings($input);
        $this->assertEquals($expected, iterator_to_array($selections));
    }

    public function testItShouldBeCountableIterableAndArrayAccessible(): void
    {
        $selections = EventSelections::buildFromLegacyEventStrings('[AR,all,1],[FL,all,1]');

        // Countable
        $this->assertCount(2, $selections);

        // Iterable
        $paths = [];
        foreach ($selections as $path) {
            $paths[] = $path;
        }
        $this->assertEquals(['HEK>>Active Region', 'HEK>>Flare'], $paths);

        // ArrayAccess
        $this->assertEquals('HEK>>Active Region', $selections[0]);
        $this->assertEquals('HEK>>Flare', $selections[1]);
        $this->assertTrue(isset($selections[0]));
        $this->assertFalse(isset($selections[99]));
        $this->assertNull($selections[99]);
    }
}
