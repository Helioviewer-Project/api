<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Timeline\SwimLaner;

final class SwimLanerTest extends TestCase
{
    private SwimLaner $swimLaner;

    protected function setUp(): void
    {
        $this->swimLaner = new SwimLaner();
    }

    public function testItShouldReturnEmptyForEmptyInput(): void
    {
        $this->assertEquals([], $this->swimLaner->assign([]));
    }

    public function testItShouldAssignSameLaneToNonOverlappingEvents(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 200, 'label' => 'e1'],
                    ['x' => 300, 'x2' => 400, 'label' => 'e2'],
                    ['x' => 500, 'x2' => 600, 'label' => 'e3'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['AR']['data'];

        // All in the same lane since they don't overlap
        $this->assertEquals($data[0]['y'], $data[1]['y']);
        $this->assertEquals($data[1]['y'], $data[2]['y']);
    }

    public function testItShouldAssignDifferentLanesToOverlappingEvents(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 300, 'label' => 'e1'],
                    ['x' => 200, 'x2' => 400, 'label' => 'e2'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['AR']['data'];

        // Different lanes since they overlap
        $this->assertNotEquals($data[0]['y'], $data[1]['y']);
    }

    public function testItShouldReuseLanesForSequentialEvents(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 200, 'label' => 'e1'],
                    ['x' => 150, 'x2' => 250, 'label' => 'e2'],  // overlaps e1
                    ['x' => 200, 'x2' => 300, 'label' => 'e3'],  // fits after e1
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['AR']['data'];

        // e1 and e2 overlap → different lanes
        $this->assertNotEquals($data[0]['y'], $data[1]['y']);
        // e3 starts where e1 ends → reuses e1's lane
        $this->assertEquals($data[0]['y'], $data[2]['y']);
    }

    public function testItShouldHandleSingleEvent(): void
    {
        $series = [
            'FL' => [
                'data' => [
                    ['x' => 100, 'x2' => 200, 'label' => 'e1'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['FL']['data'];

        $this->assertCount(1, $data);
        $this->assertArrayHasKey('y', $data[0]);
    }

    public function testItShouldHandleMultipleEventTypes(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 300, 'label' => 'ar1'],
                ]
            ],
            'FL' => [
                'data' => [
                    ['x' => 100, 'x2' => 300, 'label' => 'fl1'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);

        // Both get y values
        $this->assertArrayHasKey('y', $result['AR']['data'][0]);
        $this->assertArrayHasKey('y', $result['FL']['data'][0]);
    }

    public function testItShouldStackThreeOverlappingEvents(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 500, 'label' => 'e1'],
                    ['x' => 200, 'x2' => 500, 'label' => 'e2'],
                    ['x' => 300, 'x2' => 500, 'label' => 'e3'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['AR']['data'];

        // All three overlap → all different lanes
        $lanes = [$data[0]['y'], $data[1]['y'], $data[2]['y']];
        $this->assertCount(3, array_unique($lanes));
    }

    public function testItShouldHandleExactBoundaryTouch(): void
    {
        // e2 starts exactly when e1 ends — should fit same lane
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 200, 'label' => 'e1'],
                    ['x' => 200, 'x2' => 300, 'label' => 'e2'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $data = $result['AR']['data'];

        // x >= x2 means fits → same lane
        $this->assertEquals($data[0]['y'], $data[1]['y']);
    }

    public function testItShouldPreserveOtherEventFields(): void
    {
        $series = [
            'AR' => [
                'data' => [
                    ['x' => 100, 'x2' => 200, 'label' => 'test', 'foo' => 'bar'],
                ]
            ]
        ];

        $result = $this->swimLaner->assign($series);
        $event = $result['AR']['data'][0];

        $this->assertEquals('test', $event['label']);
        $this->assertEquals('bar', $event['foo']);
        $this->assertArrayHasKey('y', $event);
    }
}
