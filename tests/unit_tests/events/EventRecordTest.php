<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventRecord;

final class EventRecordTest extends TestCase
{
    public function testItKeepsTheFieldsTheRendererReads(): void
    {
        $record = EventRecord::fromWire([
            'remote_id' => 'ivo://helio-informatics.org/AR_SPoCA_20240101',
            'path'      => 'HEK>>Active Region>>SPoCA',
            'label'     => 'AR 13700',
            'type'      => 'AR',
            'pin'       => 'AR',
            'start'     => '2024-01-01T00:00:00',
            'end'       => '2024-01-02T00:00:00',
            'hv_hpc_x'  => -119.0,
            'hv_hpc_y'  => 570.2,
            'footprint' => [],
        ]);

        $this->assertSame('AR 13700', $record->label());
        $this->assertSame('AR', $record->type());
        $this->assertSame('AR', $record->pin());
        $this->assertSame('HEK', $record->source());
        $this->assertSame(-119.0, $record->hpcX());
        $this->assertSame(570.2, $record->hpcY());
        $this->assertFalse($record->hasFootprint());
        $this->assertSame([], $record->footprintShifted(1.0, 2.0));
    }

    public function testItDefaultsTheMissingFieldsAsTheContextDid(): void
    {
        $record = EventRecord::fromWire([]);

        $this->assertSame('', $record->label());
        $this->assertSame('UNK', $record->type());
        $this->assertSame('UNK', $record->pin());   // the pin falls back to the type
        $this->assertSame('', $record->source());
        $this->assertSame(0.0, $record->hpcX());
        $this->assertSame(0.0, $record->hpcY());
        $this->assertFalse($record->hasFootprint());
    }

    public function testItGivesTheFootprintBackShiftedWithTheFarSidePointsFlagged(): void
    {
        $record = EventRecord::fromWire([
            'path'      => 'WSA>>Coronal Hole>>SO',
            'footprint' => [
                [
                    ['x' => -13.17577747697942, 'y' => 951.2498815628871],
                    ['x' => -26.70579457655549, 'y' => 949.6555281706842, 'visible' => false],
                    ['x' => -13.528724390547723, 'y' => 951.3045634299168],
                ],
                [
                    ['x' => 16.70662843456424, 'y' => 952.6049734533182, 'visible' => true],
                ],
            ],
        ]);

        $this->assertTrue($record->hasFootprint());
        $footprint = $record->footprintShifted(250.49940927326713, -25.57105852276112);

        // The same values the API sent plus the delta, in the wire shape: a list
        // of rings of {x, y}, visible=false on the far-side point only.
        $this->assertSame([
            [
                ['x' => -13.17577747697942 + 250.49940927326713, 'y' => 951.2498815628871 - 25.57105852276112],
                ['x' => -26.70579457655549 + 250.49940927326713, 'y' => 949.6555281706842 - 25.57105852276112, 'visible' => false],
                ['x' => -13.528724390547723 + 250.49940927326713, 'y' => 951.3045634299168 - 25.57105852276112],
            ],
            [
                ['x' => 16.70662843456424 + 250.49940927326713, 'y' => 952.6049734533182 - 25.57105852276112],
            ],
        ], $footprint);
        $this->assertArrayNotHasKey('visible', $footprint[0][0]);
        $this->assertArrayNotHasKey('visible', $footprint[1][0]);
    }

    public function testAnEmptyRingStaysAnEmptyRing(): void
    {
        $record = EventRecord::fromWire(['footprint' => [[], [['x' => 1.0, 'y' => 2.0]]]]);

        $this->assertSame([[], [['x' => 1.0, 'y' => 2.0]]], $record->footprintShifted(0.0, 0.0));
    }
}
