<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventRegionSvg;

final class EventRegionSvgTest extends TestCase
{
    /** A 3-point pixel polygon. */
    private function tri(): array
    {
        return [['x' => 0.0, 'y' => 0.0], ['x' => 10.0, 'y' => 0.0], ['x' => 10.0, 'y' => 10.0]];
    }

    public function testEmptyProducesTheThreeGroupsAtTheRightSize(): void
    {
        $svg = (new EventRegionSvg([], 100, 80))->toSvg();
        $this->assertStringStartsWith('<svg', $svg);
        $this->assertStringContainsString('width="100"', $svg);
        $this->assertStringContainsString('height="80"', $svg);
        $this->assertStringContainsString('<g opacity="0.18">', $svg); // the flat tint layer
        $this->assertStringNotContainsString('<mask', $svg);
    }

    public function testEventWithFillsEmitsOwnFillMasksAndAllStyles(): void
    {
        $rendered = [[
            'hex'    => 'FEF38E',
            'tints'  => [$this->tri()],
            'ghosts' => [['pts' => $this->tri(), 'closed' => false]],
            'fills'  => [$this->tri()],
        ]];
        $svg = (new EventRegionSvg($rendered, 200, 150))->toSvg();

        // per-event own-fill masks: black cuts the tint, 45% grey dims the ghost
        $this->assertStringContainsString('<mask id="tc0"', $svg);
        $this->assertStringContainsString('<mask id="gd0"', $svg);
        $this->assertStringContainsString('fill="#737373"', $svg);
        // the tint sits in the flat 0.18 group and is masked
        $this->assertStringContainsString('<g opacity="0.18"><g mask="url(#tc0)">', $svg);
        $this->assertStringContainsString('mask="url(#gd0)"', $svg);
        // an open ghost run -> polyline, dashed @ 0.55
        $this->assertStringContainsString('<polyline', $svg);
        $this->assertStringContainsString('stroke-opacity="0.55"', $svg);
        $this->assertStringContainsString('stroke-dasharray="5,4"', $svg);
        // fill @0.4 with black stroke @0.533, in the event colour
        $this->assertStringContainsString('fill-opacity="0.4"', $svg);
        $this->assertStringContainsString('stroke-opacity="0.533"', $svg);
        $this->assertStringContainsString('fill="#FEF38E"', $svg);
    }

    public function testEventWithoutFillsHasNoMasksAndClosedGhostIsPolygon(): void
    {
        // all-behind event: a tint + a closed ghost, no near-side fills → no masks.
        $rendered = [[
            'hex'    => 'B0C4FF',
            'tints'  => [$this->tri()],
            'ghosts' => [['pts' => $this->tri(), 'closed' => true]],
            'fills'  => [],
        ]];
        $svg = (new EventRegionSvg($rendered, 100, 100))->toSvg();

        $this->assertStringNotContainsString('<mask', $svg);
        $this->assertStringNotContainsString('mask="url', $svg);
        $this->assertStringContainsString('<polygon', $svg); // closed ghost (>=3 pts) -> polygon
    }
}
