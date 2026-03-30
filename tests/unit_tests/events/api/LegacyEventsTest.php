<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Api\LegacyEvents;

final class LegacyEventsTest extends TestCase
{
    private LegacyEvents $converter;

    protected function setUp(): void
    {
        $this->converter = new LegacyEvents();
    }

    public static function convertProvider(): array
    {
        return [
            'single_event_single_group' => [
                // eventTypes
                [
                    ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                        ['name' => 'SPoCA', 'event_ids' => ['evt1']]
                    ]]
                ],
                // events
                [
                    'evt1' => ['label' => 'AR 12345', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0]
                ],
                // observations
                [
                    'evt1' => ['hv_hpc_x' => 105.0, 'hv_hpc_y' => 210.0]
                ],
                // expected categories count
                1,
                // expected first category pin
                'AR',
                // expected first event hv_hpc_x
                105.0,
            ],
            'multiple_groups' => [
                [
                    ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                        ['name' => 'SPoCA', 'event_ids' => ['evt1']],
                        ['name' => 'SHARP', 'event_ids' => ['evt2']],
                    ]]
                ],
                [
                    'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                    'evt2' => ['label' => 'AR 2', 'type' => 'AR', 'hv_hpc_x' => 300.0, 'hv_hpc_y' => 400.0],
                ],
                [
                    'evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0],
                    'evt2' => ['hv_hpc_x' => 301.0, 'hv_hpc_y' => 401.0],
                ],
                1,
                'AR',
                101.0,
            ],
            'multiple_categories' => [
                [
                    ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                        ['name' => 'SPoCA', 'event_ids' => ['evt1']]
                    ]],
                    ['pin' => 'FL', 'name' => 'Flare', 'groups' => [
                        ['name' => 'SWPC', 'event_ids' => ['evt2']]
                    ]],
                ],
                [
                    'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                    'evt2' => ['label' => 'FL 1', 'type' => 'FL', 'hv_hpc_x' => 500.0, 'hv_hpc_y' => 600.0],
                ],
                [
                    'evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0],
                    'evt2' => ['hv_hpc_x' => 501.0, 'hv_hpc_y' => 601.0],
                ],
                2,
                'AR',
                101.0,
            ],
            'empty_event_types' => [
                [],
                [],
                [],
                0,
                null,
                null,
            ],
            'empty_observations' => [
                [
                    ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                        ['name' => 'SPoCA', 'event_ids' => ['evt1']]
                    ]]
                ],
                [
                    'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                ],
                [],
                0,
                null,
                null,
            ],
        ];
    }

    /**
     * @dataProvider convertProvider
     */
    public function testItShouldConvertToLegacyFormat(
        array $eventTypes,
        array $events,
        array $obs,
        int $expectedCategoryCount,
        ?string $expectedFirstPin,
        ?float $expectedFirstHpcX
    ): void {
        $result = $this->converter->convert($eventTypes, $events, $obs);

        $this->assertCount($expectedCategoryCount, $result);

        if ($expectedFirstPin !== null) {
            $this->assertEquals($expectedFirstPin, $result[0]['pin']);
        }

        if ($expectedFirstHpcX !== null) {
            $event = $result[0]['groups'][0]['data'][0];
            $this->assertEquals($expectedFirstHpcX, $event['hv_hpc_x']);
            $this->assertEquals($expectedFirstHpcX, $event['hv_hpc_x_final']);
        }
    }

    public function testItShouldMergeRotatedCoords(): void
    {
        $result = $this->converter->convert(
            [['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                ['name' => 'SPoCA', 'event_ids' => ['evt1']]
            ]]],
            ['evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0]],
            ['evt1' => ['hv_hpc_x' => 105.0, 'hv_hpc_y' => 210.0]]
        );

        $event = $result[0]['groups'][0]['data'][0];
        $this->assertEquals(105.0, $event['hv_hpc_x']);
        $this->assertEquals(210.0, $event['hv_hpc_y']);
        $this->assertEquals(105.0, $event['hv_hpc_x_final']);
        $this->assertEquals(210.0, $event['hv_hpc_y_final']);
    }

    public function testItShouldSkipInactiveEvents(): void
    {
        $result = $this->converter->convert(
            [['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                ['name' => 'SPoCA', 'event_ids' => ['evt1', 'evt2']]
            ]]],
            [
                'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                'evt2' => ['label' => 'AR 2', 'type' => 'AR', 'hv_hpc_x' => 300.0, 'hv_hpc_y' => 400.0],
            ],
            // only evt1 active
            ['evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0]]
        );

        $data = $result[0]['groups'][0]['data'];
        $this->assertCount(1, $data);
        $this->assertEquals('AR 1', $data[0]['label']);
    }

    public function testItShouldShiftFootprintByRotationDelta(): void
    {
        $result = $this->converter->convert(
            [['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                ['name' => 'SPoCA', 'event_ids' => ['evt1']]
            ]]],
            ['evt1' => [
                'label' => 'AR 1', 'type' => 'AR',
                'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0,
                'footprint' => [
                    ['x' => 98.0, 'y' => 198.0],
                    ['x' => 102.0, 'y' => 202.0],
                ]
            ]],
            ['evt1' => ['hv_hpc_x' => 105.0, 'hv_hpc_y' => 210.0]]
        );

        $footprint = $result[0]['groups'][0]['data'][0]['footprint'];
        // dx = 105 - 100 = 5, dy = 210 - 200 = 10
        $this->assertEquals(103.0, $footprint[0]['x']);
        $this->assertEquals(208.0, $footprint[0]['y']);
        $this->assertEquals(107.0, $footprint[1]['x']);
        $this->assertEquals(212.0, $footprint[1]['y']);
    }

    public function testItShouldExcludeEmptyGroups(): void
    {
        $result = $this->converter->convert(
            [['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                ['name' => 'SPoCA', 'event_ids' => ['evt1']],
                ['name' => 'SHARP', 'event_ids' => ['evt2']],
            ]]],
            [
                'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                'evt2' => ['label' => 'AR 2', 'type' => 'AR', 'hv_hpc_x' => 300.0, 'hv_hpc_y' => 400.0],
            ],
            // only evt1 active, evt2 not → SHARP group should be excluded
            ['evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0]]
        );

        $this->assertCount(1, $result[0]['groups']);
        $this->assertEquals('SPoCA', $result[0]['groups'][0]['name']);
    }

    public function testItShouldExcludeEmptyCategories(): void
    {
        $result = $this->converter->convert(
            [
                ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                    ['name' => 'SPoCA', 'event_ids' => ['evt1']]
                ]],
                ['pin' => 'FL', 'name' => 'Flare', 'groups' => [
                    ['name' => 'SWPC', 'event_ids' => ['evt2']]
                ]],
            ],
            [
                'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
                'evt2' => ['label' => 'FL 1', 'type' => 'FL', 'hv_hpc_x' => 500.0, 'hv_hpc_y' => 600.0],
            ],
            // only evt1 active → FL category excluded
            ['evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0]]
        );

        $this->assertCount(1, $result);
        $this->assertEquals('AR', $result[0]['pin']);
    }

    public function testItShouldConvertAllTimestamps(): void
    {
        $batchResponse = [
            'event_types' => [
                ['pin' => 'AR', 'name' => 'Active Region', 'groups' => [
                    ['name' => 'SPoCA', 'event_ids' => ['evt1']]
                ]]
            ],
            'events' => [
                'evt1' => ['label' => 'AR 1', 'type' => 'AR', 'hv_hpc_x' => 100.0, 'hv_hpc_y' => 200.0],
            ],
            'observations' => [
                '2024-01-15 12:00:00' => ['evt1' => ['hv_hpc_x' => 101.0, 'hv_hpc_y' => 201.0]],
                '2024-01-15 12:01:00' => ['evt1' => ['hv_hpc_x' => 102.0, 'hv_hpc_y' => 202.0]],
                '2024-01-15 12:02:00' => ['evt1' => ['hv_hpc_x' => 103.0, 'hv_hpc_y' => 203.0]],
            ]
        ];

        $result = $this->converter->convertAll($batchResponse);

        $this->assertCount(3, $result);
        $this->assertArrayHasKey('2024-01-15 12:00:00', $result);
        $this->assertArrayHasKey('2024-01-15 12:01:00', $result);
        $this->assertArrayHasKey('2024-01-15 12:02:00', $result);

        // Each timestamp has its own rotated coords
        $this->assertEquals(101.0, $result['2024-01-15 12:00:00'][0]['groups'][0]['data'][0]['hv_hpc_x']);
        $this->assertEquals(102.0, $result['2024-01-15 12:01:00'][0]['groups'][0]['data'][0]['hv_hpc_x']);
        $this->assertEquals(103.0, $result['2024-01-15 12:02:00'][0]['groups'][0]['data'][0]['hv_hpc_x']);
    }
}
