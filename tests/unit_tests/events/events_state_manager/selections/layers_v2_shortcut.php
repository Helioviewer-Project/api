<?php

return [
'layers_v2 populated: shortcut overrides layers entirely' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            'layers_v2' => ['HEK>>Active Region>>SPoCA', 'HEK>>Flare'],
        ],
    ],
    'expected' => ['HEK>>Active Region>>SPoCA', 'HEK>>Flare'],
],
'layers_v2 empty: falls through to legacy layers' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            'layers_v2' => [],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
'layers_v2 shortcut bypasses pin lookup even for unknown pins' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [['event_type' => 'ZZ', 'frms' => ['all'], 'event_instances' => []]],
            'layers_v2' => ['HEK>>Anything>>Goes'],
        ],
    ],
    'expected' => ['HEK>>Anything>>Goes'],
],
'layers_v2 shortcut works for CCMC source' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [['event_type' => 'C3', 'frms' => ['all'], 'event_instances' => []]],
            'layers_v2' => ['CCMC>>DONKI>>CME', 'CCMC>>Solar Flare Predictions'],
        ],
    ],
    'expected' => ['CCMC>>DONKI>>CME', 'CCMC>>Solar Flare Predictions'],
],
'layers_v2 shortcut works for RHESSI source' => [
    'state' => [
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers'    => [],
            'layers_v2' => ['RHESSI>>Solar Flares>>Clean'],
        ],
    ],
    'expected' => ['RHESSI>>Solar Flares>>Clean'],
],
];
