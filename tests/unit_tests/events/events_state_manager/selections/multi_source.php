<?php

return [
'multi-source: HEK + CCMC visible, RHESSI muted' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FP', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => false, 'labels_visible' => false,
            'layers' => [['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Active Region', 'CCMC>>Solar Flare Predictions'],
],
'multi-source: all three visible, each contributing a Level 1 path' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'C3', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => [
        'HEK>>Active Region',
        'CCMC>>DONKI',
        'RHESSI>>Solar Flares',
    ],
],
'multi-source: each source at a different level' => [
    'state' => [
        // HEK: level 1 (all)
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
        // CCMC: level 2 (named FRM)
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'C3', 'frms' => ['CCMC-DONKI'], 'event_instances' => []]],
        ],
        // RHESSI: layers_v2 shortcut
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []]],
            'layers_v2' => ['RHESSI>>Solar Flares>>Custom'],
        ],
    ],
    'expected' => [
        'HEK>>Active Region',
        'CCMC>>DONKI>>CCMC-DONKI',
        'RHESSI>>Solar Flares>>Custom',
    ],
],
'multi-source: CCMC muted, HEK + RHESSI visible' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => false, 'labels_visible' => false,
            'layers' => [['event_type' => 'C3', 'frms' => ['all'], 'event_instances' => []]],
        ],
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'F2', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Active Region', 'RHESSI>>Solar Flares'],
],
];
