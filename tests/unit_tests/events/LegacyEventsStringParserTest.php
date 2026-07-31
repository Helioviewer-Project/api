<?php declare(strict_types=1);

/**
 * Parse-case fixtures ported verbatim from EventSelectionsTest -- the parsing
 * logic moved unchanged from EventSelections::buildFromLegacyEventStrings to
 * LegacyEventsStringParser::parse(). The ArrayAccess/Countable wrapper tests
 * did not port: the parser returns a plain string[], and that wrapper behavior
 * retires with EventSelections.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\LegacyEventsStringParser;

final class LegacyEventsStringParserTest extends TestCase
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
            'wsa_source' => [
                '[MC,SO,1]',
                ['WSA>>Magnetic Connectivity>>SO'],
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
    public function testItParsesLegacyStringsToPaths(string $input, array $expected): void
    {
        $this->assertSame($expected, LegacyEventsStringParser::parse($input));
    }

    public function testSelectionsFromUrlParamsPairsPathsWithVisibility(): void
    {
        [$selections, $visibility] = LegacyEventsStringParser::selectionsFromUrlParams('[AR,all,1],[MC,SO,1]', true);

        $this->assertSame(['HEK>>Active Region', 'WSA>>Magnetic Connectivity>>SO'], $selections);

        // One entry per known source; markers always visible, labels from the flag.
        $this->assertEqualsCanonicalizing(['HEK', 'CCMC', 'RHESSI', 'WSA'], array_keys($visibility));
        foreach ($visibility as $flags) {
            $this->assertSame(['marker_visibility' => true, 'label_visibility' => true], $flags);
        }
    }

    public function testSelectionsFromUrlParamsPropagatesLabelsFlagFalse(): void
    {
        [, $visibility] = LegacyEventsStringParser::selectionsFromUrlParams('[AR,all,1]', false);

        $this->assertFalse($visibility['HEK']['label_visibility']);
        $this->assertTrue($visibility['HEK']['marker_visibility']);
    }
}
