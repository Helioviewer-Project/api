<?php

return [
'level 1: single layer with frms=[all] emits SOURCE>>LABEL' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
'level 1: multiple all-layers emit one path each' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []],
                ['event_type' => 'FL', 'frms' => ['all'], 'event_instances' => []],
            ],
        ],
    ],
    'expected' => ['HEK>>Active Region', 'HEK>>Flare'],
],
'level 1: CCMC C3 all -> CCMC>>DONKI' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'C3', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['CCMC>>DONKI'],
],
'level 1: CCMC FP all -> CCMC>>Solar Flare Predictions' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FP', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['CCMC>>Solar Flare Predictions'],
],
'level 1: RHESSI F2 all -> RHESSI>>Solar Flares' => [
    'state' => [
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['RHESSI>>Solar Flares'],
],
];
