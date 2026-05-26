<?php

return [
'same FRM in multiple layers deduplicates' => [
    'state' => [
        'tree_HEK' => [
            'id' => 'HEK', 'markers_visible' => true, 'labels_visible' => true,
            'layers' => [
                ['event_type' => 'FL', 'frms' => ['NOAA_SWPC'], 'event_instances' => []],
                ['event_type' => 'FL', 'frms' => ['NOAA_SWPC'], 'event_instances' => []],
            ],
        ],
    ],
    'expected' => ['HEK>>Flare>>NOAA_SWPC', 'HEK>>Flare>>NOAA SWPC'],
],
];
