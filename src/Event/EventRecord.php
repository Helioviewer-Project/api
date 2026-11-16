<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * One event of the events API, held the way a movie can afford to hold
 * thousands of them: the fields the renderer reads, and the footprint packed.
 *
 * On the wire a footprint is a list of rings, each a list of points
 * {x, y, visible?}, with visible=false on the points behind the sun and the
 * key absent otherwise. Decoded, PHP keeps every point as its own hash table,
 * about 400 bytes for two doubles: the 1.6 million points of a month of
 * coronal holes come to 600 MB. Here a ring is one string of its doubles
 * (x1, y1, x2, y2, ... with pack 'd', 16 bytes a point, bit for bit the
 * values the API sent) and one string of the indexes of its far-side points
 * (pack 'V'); the month comes to 45 MB. footprintShifted() gives a ring back
 * in the wire shape, every point moved by a frame's rotation delta, which is
 * what the renderer draws.
 *
 * The fields the renderer never reads (remote id, times, version, the
 * coordinate system) are not kept.
 */
class EventRecord
{
    /** @param list<array{string, string}> $rings per ring: the packed points, the packed far-side indexes */
    private function __construct(
        private string $label,
        private string $type,
        private string $pin,
        private string $source,
        private float $hpcX,
        private float $hpcY,
        private array $rings
    ) {
    }

    /** From the event dict of a frames_with_selections reply. */
    public static function fromWire(array $data): self
    {
        $type = $data['type'] ?? 'UNK';
        $rings = [];
        foreach ($data['footprint'] ?? [] as $ring) {
            $flat = [];
            $far = [];
            $i = 0;
            foreach ($ring as $p) {
                $flat[] = (float) ($p['x'] ?? 0.0);
                $flat[] = (float) ($p['y'] ?? 0.0);
                if (($p['visible'] ?? null) === false) {
                    $far[] = $i;
                }
                $i++;
            }
            $rings[] = [pack('d*', ...$flat), pack('V*', ...$far)];
        }
        return new self(
            (string) ($data['label'] ?? ''),
            (string) $type,
            (string) ($data['pin'] ?? $type),
            explode('>>', $data['path'] ?? '', 2)[0],
            (float) ($data['hv_hpc_x'] ?? 0.0),
            (float) ($data['hv_hpc_y'] ?? 0.0),
            $rings
        );
    }

    public function label(): string
    {
        return $this->label;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function pin(): string
    {
        return $this->pin;
    }

    /** The leading SOURCE token of the event's path, e.g. "HEK>>Active Region>>SPoCA" -> "HEK"; '' when there is no path. */
    public function source(): string
    {
        return $this->source;
    }

    public function hpcX(): float
    {
        return $this->hpcX;
    }

    public function hpcY(): float
    {
        return $this->hpcY;
    }

    public function hasFootprint(): bool
    {
        return $this->rings !== [];
    }

    /**
     * The footprint in the wire shape, every point moved by ($dx, $dy): a list
     * of rings of {x, y}, with visible=false on the far-side points only, so
     * the renderer's far-side test stays a strict === false.
     *
     * @return list<list<array{x: float, y: float, visible?: false}>>
     */
    public function footprintShifted(float $dx, float $dy): array
    {
        $footprint = [];
        foreach ($this->rings as [$packedPoints, $packedFar]) {
            $values = unpack('d*', $packedPoints);           // 1-based: x1, y1, x2, y2, ...
            $far = $packedFar === '' ? [] : array_flip(unpack('V*', $packedFar));
            $ring = [];
            $n = count($values) >> 1;
            for ($i = 0; $i < $n; $i++) {
                $point = ['x' => $values[2 * $i + 1] + $dx, 'y' => $values[2 * $i + 2] + $dy];
                if (isset($far[$i])) {
                    $point['visible'] = false;
                }
                $ring[] = $point;
            }
            $footprint[] = $ring;
        }
        return $footprint;
    }
}
