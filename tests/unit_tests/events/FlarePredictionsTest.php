<?php declare(strict_types=1);

use FlareScoreboard\HapiIterator;
use PHPUnit\Framework\TestCase;
include_once HV_ROOT_DIR . '/../src/Event/FlarePredictions.php';

/**
 * Small test harness to make protected methods public
 */
final class FlarePredictionsHarness extends FlarePredictions
{
    static public function predictionsToEvents(HapiIterator &$predictions): array
    {
        return FlarePredictions::predictionsToEvents($predictions);
    }
}

final class FlarePredictionsTest extends TestCase
{
    public function testGetScoreboardEvents(): void
    {
        $predictions = FlarePredictions::getEvents('2020-01-01T00:00:00', '2020-01-01T23:59:59');
        $this->assertNotEquals(
            0,
            count($predictions)
        );
        $event = $predictions[0];
        $this->assertArrayHasKey("event_coordsys", $event);
    }

}