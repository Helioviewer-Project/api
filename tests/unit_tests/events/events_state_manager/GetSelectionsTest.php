<?php

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventsStateManager;

/**
 * Tests for EventsStateManager::getSelections() - building path-prefix
 * selection strings ("SOURCE>>LABEL[>>FRM]") for the events API
 * frames_with_selections endpoint.
 *
 * Fixtures live in ./selections/, one PHP file per behaviour group. Each
 * fixture file returns an array of cases keyed by description. The provider
 * concatenates them in the order listed below; PHPUnit prints the case key
 * verbatim on failure.
 */
class GetSelectionsTest extends TestCase
{
    /** Ordered list of fixture files under ./selections/ */
    private const FIXTURES = [
        'empty_muted.php',
        'markers_visible_coercion.php',
        'level_1_all_wildcard.php',
        'level_2_explicit_frms.php',
        'level_3_event_instances.php',
        'layers_v2_shortcut.php',
        'unknown_malformed.php',
        'deduplication.php',
        'multi_source.php',
    ];

    public static function selectionsProvider(): array
    {
        $cases = [];
        foreach (self::FIXTURES as $file) {
            $cases = array_merge($cases, require __DIR__ . '/selections/' . $file);
        }
        return $cases;
    }

    /**
     * @dataProvider selectionsProvider
     */
    public function testItShouldReturnExpectedSelections(array $state, array $expected): void
    {
        $manager = EventsStateManager::buildFromEventsState($state);
        $this->assertEquals($expected, $manager->getSelections());
    }
}
