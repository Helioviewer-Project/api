<?php

return [
'unknown pin is skipped' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'ZZ', 'frms' => ['all'], 'event_instances' => []],
            ],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
'UNK sentinel pin is silently skipped (no unknown-pin log fired)' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'UNK', 'frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'AR',  'frms' => ['all'], 'event_instances' => []],
            ],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
'layer missing event_type key is skipped' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []],
            ],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
'unknown pin in CCMC source is skipped' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'C3', 'frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'XY', 'frms' => ['all'], 'event_instances' => []], // not in CCMC map
            ],
        ],
    ],
    'expected' => ['CCMC>>DONKI'],
],
'unknown pin in RHESSI source is skipped' => [
    'state' => [
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'ZZ', 'frms' => ['all'], 'event_instances' => []], // not in RHESSI map
            ],
        ],
    ],
    'expected' => ['RHESSI>>Solar Flares'],
],
];
