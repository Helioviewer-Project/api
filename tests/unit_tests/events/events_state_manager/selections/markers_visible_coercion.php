<?php

/**
 * Edge cases for non-boolean values in markers_visible.
 *
 * The constructor and getSelections() both decide whether to skip a source
 * with `array_key_exists('markers_visible', $s) && $s['markers_visible'] != true`,
 * which is a LOOSE comparison. PHP's loose comparison of a non-empty string
 * against true evaluates to true, so the source is processed even for the
 * string 'false'. That gotcha is locked in below so it stays intentional.
 */

return [
    "markers_visible='true' (string) is truthy under loose comparison: processes" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => 'true', 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => ['HEK>>Active Region'],
    ],
    "markers_visible='false' (string) is ALSO truthy (non-empty string): processes -- GOTCHA" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => 'false', 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => ['HEK>>Active Region'],
    ],
    "markers_visible='' (empty string) is falsy: skipped" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => '', 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => [],
    ],
    "markers_visible='nonsense-for-bool' is truthy: processes" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => 'nonsense-for-bool', 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => ['HEK>>Active Region'],
    ],
    "markers_visible=0 (int) is falsy: skipped" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => 0, 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => [],
    ],
    "markers_visible=1 (int) is truthy: processes" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => 1, 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => ['HEK>>Active Region'],
    ],
    "markers_visible=null is falsy: skipped" => [
        'state' => [
            'tree_HEK' => [
                'id' => 'HEK', 'markers_visible' => null, 'labels_visible' => true,
                'layers' => [['event_type' => 'AR', 'frms' => ['all'], 'event_instances' => []]],
            ],
        ],
        'expected' => [],
    ],
];
