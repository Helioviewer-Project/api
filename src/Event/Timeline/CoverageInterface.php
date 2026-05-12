<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Timeline;

use Helioviewer\Api\Event\Api\EventsApiInterface;

/**
 * Interface for timeline coverage strategies.
 */
interface CoverageInterface
{
    /**
     * @param EventsApiInterface $eventsApi
     * @param TimeRange $range Extended time range
     * @param array $paths Selection paths from EventSelections
     * @param string $resolution Resolution string
     * @return array Series data for the timeline
     */
    public function execute(EventsApiInterface $eventsApi, TimeRange $range, array $paths, string $resolution): array;
}
