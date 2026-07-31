<?php declare(strict_types=1);

namespace Helioviewer\Api\Event\Timeline;

use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiInterface;

/**
 * Orchestrator for event coverage timeline queries.
 *
 * Validates input, calculates resolution from time range,
 * picks the right coverage strategy (minute vs aggregated),
 * and returns JSON for the frontend timeline.
 *
 * Usage:
 *   $timeline = new Timeline($paths, $start, $end, $current, $eventsApi);
 *   echo $timeline->execute();
 */
class Timeline
{
    private TimeRange $range;
    private string $resolution;
    /** @var string[] canonical SOURCE>>Label[>>FRM] selection paths */
    private array $paths;
    private EventsApiInterface $eventsApi;
    private CoverageInterface $strategy;

    /**
     * @param string[] $paths Canonical selection paths, e.g.
     *                        ['HEK>>Active Region', 'WSA>>Magnetic Connectivity>>SO']
     * @param mixed $startTimestamp Start time in milliseconds
     * @param mixed $endTimestamp End time in milliseconds
     * @param mixed $currentTimestamp Current observation time in milliseconds
     * @param EventsApiInterface|null $eventsApi Optional API client (for testing/DI)
     * @param CoverageInterface|null $strategy Optional strategy override (for testing)
     * @throws \InvalidArgumentException If timestamps are invalid
     */
    public function __construct(
        array $paths,
        $startTimestamp,
        $endTimestamp,
        $currentTimestamp,
        ?EventsApiInterface $eventsApi = null,
        ?CoverageInterface $strategy = null
    ) {
        // Validate and store time range
        $this->range = new TimeRange($startTimestamp, $endTimestamp, $currentTimestamp);

        // Calculate resolution from the time range
        $this->resolution = Resolution::fromRange($this->range->range());

        // Canonical selection paths, as sent by the eventsDataCoverage POST body.
        $this->paths = array_values($paths);

        // API client — use provided or create default
        $this->eventsApi = $eventsApi ?? new EventsApi();

        // Coverage strategy — auto-select based on resolution, or use provided override
        $this->strategy = $strategy ?? ($this->resolution === 'm'
            ? new MinuteCoverage(new SwimLaner())
            : new AggregatedCoverage());
    }

    /**
     * Execute the timeline query and return JSON string.
     *
     * @return string JSON array of series data for the frontend
     */
    public function execute(): string
    {
        $data = $this->strategy->execute(
            $this->eventsApi,
            $this->range,
            $this->paths,
            $this->resolution
        );

        return json_encode($data);
    }

    // Getters for testing
    public function getResolution(): string { return $this->resolution; }
    public function getRange(): TimeRange { return $this->range; }
    /** @return string[] */
    public function getPaths(): array { return $this->paths; }
}
