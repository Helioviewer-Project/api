<?php

return [
'empty state' => [
    'state'    => [],
    'expected' => [],
],
'source with markers_visible=false produces nothing' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => false, 'labels_visible' => false,
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => [],
],
'source with markers_visible key absent still processes (historical convention)' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK',
            'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Active Region'],
],
];
