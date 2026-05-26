<?php

return [
'level 2: FRM with mixed space and underscore emits all 3 variants' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FL', 'frms' => ['Foo Bar_Baz'], 'event_instances' => []]],
        ],
    ],
    'expected' => [
        'HEK>>Flare>>Foo Bar_Baz',
        'HEK>>Flare>>Foo_Bar_Baz',
        'HEK>>Flare>>Foo Bar Baz',
    ],
],
'level 2: FRM with only underscores collapses to 2 variants' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FL', 'frms' => ['NOAA_SWPC'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA_SWPC', 'HEK>>Flare>>NOAA SWPC'],
],
'level 2: FRM with only spaces collapses to 2 variants' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FL', 'frms' => ['NOAA SWPC'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA SWPC', 'HEK>>Flare>>NOAA_SWPC'],
],
'level 2: FRM with neither space nor underscore collapses to 1 path' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FL', 'frms' => ['NOAA-SWPC'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA-SWPC'],
],
'level 2: multiple FRMs each produce their own variant set' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'FL', 'frms' => ['NOAA_SWPC', 'SPoCA'], 'event_instances' => []]],
        ],
    ],
    'expected' => [
        'HEK>>Flare>>NOAA_SWPC',
        'HEK>>Flare>>NOAA SWPC',
        'HEK>>Flare>>SPoCA',
    ],
],
'level 2: CCMC C3 with named FRM (mixed space and underscore)' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'C3', 'frms' => ['Foo Bar_Baz'], 'event_instances' => []]],
        ],
    ],
    'expected' => [
        'CCMC>>DONKI>>Foo Bar_Baz',
        'CCMC>>DONKI>>Foo_Bar_Baz',
        'CCMC>>DONKI>>Foo Bar Baz',
    ],
],
'level 2: RHESSI F2 with named FRM' => [
    'state' => [
        'tree_RHESSI' => [
            'id' => 'RHESSI', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [['event_type' => 'F2', 'frms' => ['Clean_60s'], 'event_instances' => []]],
        ],
    ],
    'expected' => ['RHESSI>>Solar Flares>>Clean_60s', 'RHESSI>>Solar Flares>>Clean 60s'],
],
];
