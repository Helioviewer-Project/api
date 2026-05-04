<?php declare(strict_types=1);
/**
 * EventSelections Class Definition
 * A helper class to parse legacy event strings and provide event selections
 *
 * @category Event
 * @package  Helioviewer
 * @author   Kasim Necdet Percinel <kasim.n.oercinel@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Event;

use ArrayAccess;
use Countable;
use IteratorAggregate;
use ArrayIterator;
use Traversable;

class EventSelections implements ArrayAccess, Countable, IteratorAggregate
{
    public static array $event_types_map = [
        'HEK' => [
            'AR' => 'Active Region',
            'CE' => 'CME',
            'CH' => 'Coronal Hole',
            'EF' => 'Emerging Flux',
            'FI' => 'Filament',
            'FL' => 'Flare',
            'SG' => 'Sigmoid',
            'CC' => 'Coronal Cavity',
            'CD' => 'Coronal Dimming',
            'CJ' => 'Coronal Jet',
            'CR' => 'Coronal Rain',
            'CW' => 'Coronal Wave',
            'ER' => 'Eruption',
            'FA' => 'Filament Activation',
            'FE' => 'Filament Eruption',
            'LP' => 'Loop',
            'OS' => 'Oscillation',
            'PG' => 'Plage',
            'SP' => 'Spray Surge',
            'SS' => 'Sunspot',
            'OT' => 'Other',
            'NR' => 'Nothing Reported',
            'TO' => 'Topological Object',
            'HY' => 'Hypothesis',
            'BU' => 'UVBurst',
            'EE' => 'Explosive Event',
            'PB' => 'Prominence Bubble',
            'PT' => 'Peacock Tail',
            'EP' => 'SEPs',
            'IC' => 'ICMEs',
            'SR' => 'SIRs',
            // 'UNK' => 'Unknown', // Not in events API
        ],
        'CCMC' => [
            'C3' => 'DONKI',
            'FP' => 'Solar Flare Predictions',
        ],
        'RHESSI' => [
            'F2' => 'Solar Flares',
        ],
    ];

    private array $selections;

    /**
     * Creates a new EventSelections
     * @param array $selections Array of selection strings like 'HEK>>Active Region>>Spoca'
     */
    private function __construct(array $selections)
    {
        $this->selections = $selections;
    }

    /**
     * Creates a new EventSelections from legacy event string
     * @param string $events_state_string Legacy event string like "[AR,all,1],[FL,NOAA_SWPC,1]"
     * @return EventSelections
     */
    public static function buildFromLegacyEventStrings(string $events_state_string): EventSelections
    {
        $selections = [];

        // Prevent possible bugs
        $events_state_string = trim($events_state_string);

        if (!empty($events_state_string)) {
            $stripped = stripslashes($events_state_string);
            // Remove only the outermost [ and ]
            if (str_starts_with($stripped, '[') && str_ends_with($stripped, ']')) {
                $stripped = substr($stripped, 1, -1);
            }
            $event_strings = explode("],[", $stripped);

            // Process individual events in string
            foreach ($event_strings as $es) {

                $event_pieces = explode(",", $es);

                // there should be 3 elements
                if (count($event_pieces) < 3) {
                    continue;
                }

                list($event_type, $combined_frms, $visible) = $event_pieces;

                // Find the source (HEK, CCMC, RHESSI) and label for this event_type
                $source = null;
                $label = null;
                foreach (self::$event_types_map as $src => $types) {
                    if (array_key_exists($event_type, $types)) {
                        $source = $src;
                        $label = $types[$event_type];
                        break;
                    }
                }

                // Skip if event_type not found in map
                if ($source === null || $label === null) {
                    continue;
                }

                $frms = explode(";", $combined_frms);

                // If 'all' or empty frms, just use SOURCE>>LABEL
                if (empty($combined_frms) || $combined_frms === 'all' || in_array('all', $frms)) {
                    $selections[] = $source . '>>' . $label;
                } else {
                    // For each specific FRM, create SOURCE>>LABEL>>FRM
                    foreach ($frms as $frm) {
                        $frm = trim($frm);
                        if (!empty($frm)) {
                            $selections[] = $source . '>>' . $label . '>>' . $frm;
                        }
                    }
                }
            }
        }

        return new self($selections);
    }

    // IteratorAggregate implementation
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->selections);
    }

    // Countable implementation
    public function count(): int
    {
        return count($this->selections);
    }

    // ArrayAccess implementation
    public function offsetExists($offset): bool
    {
        return isset($this->selections[$offset]);
    }

    public function offsetGet($offset): mixed
    {
        return $this->selections[$offset] ?? null;
    }

    public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->selections[] = $value;
        } else {
            $this->selections[$offset] = $value;
        }
    }

    public function offsetUnset($offset): void
    {
        unset($this->selections[$offset]);
    }
}
