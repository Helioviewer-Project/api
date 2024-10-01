<?php declare(strict_types=1);

/**
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

require_once HV_ROOT_DIR . "/../src/Helper/EventInterface.php";

final class EventInterfaceTest extends TestCase {
    /**
     * Issue: https://github.com/Helioviewer-Project/helioviewer.org/issues/626
     * Error was caused by Flare Scoreboard data having invalid coordinates.
     * This test gets the events which were causing the error to verify that
     * no error occurs anymore.
     */
    #[Group('event interface')]
    public function testGetEventsOnDateWithQuestionableData(): void {
        // Original error was that an exception was thrown.
        $this->expectNotToPerformAssertions();
        Helper_EventInterface::GetEvents(
            new DateTimeImmutable("2015-11-03"),
            new DateInterval('P1D'),
            new DateTimeImmutable('2015-11-03T15:00:00'),
        );
    }
}
