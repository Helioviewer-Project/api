<?php

return [
'level 3: event_instance with FRM not in frms list adds variants' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                [
                    'event_type'      => 'FL',
                    'frms'            => ['NOAA_SWPC'],
                    'event_instances' => ['flare--SDO_HMI--evt-123'],
                ],
            ],
        ],
    ],
    'expected' => [
        'HEK>>Flare>>NOAA_SWPC',
        'HEK>>Flare>>NOAA SWPC',
        'HEK>>Flare>>SDO_HMI',
        'HEK>>Flare>>SDO HMI',
    ],
],
'level 3: event_instance with FRM already in frms list contributes nothing extra' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                [
                    'event_type'      => 'FL',
                    'frms'            => ['NOAA_SWPC'],
                    'event_instances' => ['flare--NOAA_SWPC--evt-123'],
                ],
            ],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA_SWPC', 'HEK>>Flare>>NOAA SWPC'],
],
'level 3: malformed event_instance (no -- separators) is silently skipped' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                [
                    'event_type'      => 'FL',
                    'frms'            => ['NOAA_SWPC'],
                    'event_instances' => ['no-double-dashes'],
                ],
            ],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA_SWPC', 'HEK>>Flare>>NOAA SWPC'],
],
'level 3: CCMC event_instance picks up FRM' => [
    'state' => [
        'tree_CCMC' => [
            'id' => 'CCMC', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                [
                    'event_type'      => 'C3',
                    'frms'            => ['CCMC_DONKI'],
                    'event_instances' => ['donki--Alt_Source--evt-77'],
                ],
            ],
        ],
    ],
    'expected' => [
        'CCMC>>DONKI>>CCMC_DONKI',
        'CCMC>>DONKI>>CCMC DONKI',
        'CCMC>>DONKI>>Alt_Source',
        'CCMC>>DONKI>>Alt Source',
    ],
],
];
