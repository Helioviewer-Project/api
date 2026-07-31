<?php declare(strict_types=1);
/**
 * EventTypeCatalogue
 *
 * The canonical SOURCE => { event_type_code => human label } map for every
 * event source Helioviewer understands. Single source of truth for turning a
 * 2-letter event-type code into a canonical "SOURCE>>Label" path segment.
 *
 * Lifted verbatim from the former EventSelections::$event_types_map so the map
 * survives that class's retirement. Consumed by EventTree (simpletree output)
 * and LegacyEventsStringParser (URL bracket-string parsing).
 *
 * @category Event
 * @package  Helioviewer
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Event;

final class EventTypeCatalogue
{
    public const MAP = [
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
        'WSA' => [
            'MC' => 'Magnetic Connectivity',
            'CH' => 'Coronal Hole',
        ],
    ];
}
