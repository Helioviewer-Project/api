<?php

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventsStateManager;

class EventManagerRegressionTest extends TestCase
{
    // Regression test for double slash problem
    public function testItShouldHandleDoubleSlashWithEventInstances()
    {
        $events_state_with_double_slash = [
            'tree_HEK' => [
                'id' => 'HEK',
                'visible' => true,
                'markers_visible' => true,
                'labels_visible' => true,
                'layer_available_visible' => true,
                'layers' => [
                    [
                        'event_instances' => [
                            'AR--SPoCA--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0FSX1NQb0NBXzIwMjUwNDA0XzE4MjMyNF8yMDI1MDMyOVQwNzAwMzRfNQ__'
                        ],
                        'event_type' => 'AR',
                        'frms' => [],
                        'open' => 1
                    ],
                    [
                        'event_instances' => [
                            'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwMzMwXzE2MzMzMl82MDkuNDYw',
                            'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwNDAxXzE5MzU0OV8yMjQyLjU1',
                            'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwMzMwXzE5MzM0NV8yMzYwLjU0'
                        ],
                        'event_type' => 'CE',
                        'frms' => [],
                        'open' => 1
                    ]
                ],
                'layers_v2' => [
                    "HEK>>Active Region>>SPoCA>>SPoCA 40563\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>502 u00b1 152 km/sec\n8 deg\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>676 u00b1 340 km/sec\n30 deg\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>676 u00b1 343 km/sec\n30 deg\n"
                ]
            ]
        ];

        $manager = EventsStateManager::buildFromEventsState($events_state_with_double_slash);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'AR' => [
                'SPoCA' => [
                    'AR--SPoCA--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0FSX1NQb0NBXzIwMjUwNDA0XzE4MjMyNF8yMDI1MDMyOVQwNzAwMzRfNQ__'
                ]
            ],
            'CE' => [
                'CACTus_(Computer_Aided_CME_Tracking)' => [
                    'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwMzMwXzE2MzMzMl82MDkuNDYw',
                    'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwNDAxXzE5MzU0OV8yMjQyLjU1',
                    'CE--CACTus_\\(Computer_Aided_CME_Tracking\\)--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0NFX0NBQ1R1cyhDb21wdXRlckFpZGVkQ01FVHJhY2tpbmcpXzIwMjUwMzMwXzE5MzM0NV8yMzYwLjU0'
                ]
            ]
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'AR' => true,
            'CE' => true,
        ]);

    }
    // Regression test for double slash problem
    public function testItShouldHandleDoubleSlashWithFRMs()
    {
        $events_state_with_double_slash = [
            'tree_HEK' => [
                'id' => 'HEK',
                'visible' => true,
                'markers_visible' => true,
                'labels_visible' => true,
                'layer_available_visible' => true,
                'layers' => [
                    [
                        'event_instances' => [
                            'AR--SPoCA--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0FSX1NQb0NBXzIwMjUwNDA0XzE4MjMyNF8yMDI1MDMyOVQwNzAwMzRfNQ__'
                        ],
                        'event_type' => 'AR',
                        'frms' => [],
                        'open' => 1
                    ],
                    [
                        'event_instances' => [],
                        'event_type' => 'CE',
                        'frms' => ['CACTus_\\(Computer_Aided_CME_Tracking\\)'],
                        'open' => 1
                    ]
                ],
                'layers_v2' => [
                    "HEK>>Active Region>>SPoCA>>SPoCA 40563\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>502 u00b1 152 km/sec\n8 deg\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>676 u00b1 340 km/sec\n30 deg\n",
                    "HEK>>CME>>CACTus (Computer Aided CME Tracking)>>676 u00b1 343 km/sec\n30 deg\n"
                ]
            ]
        ];

        $manager = EventsStateManager::buildFromEventsState($events_state_with_double_slash);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'AR' => [
                'SPoCA' => [
                    'AR--SPoCA--aXZvOi8vaGVsaW8taW5mb3JtYXRpY3Mub3JnL0FSX1NQb0NBXzIwMjUwNDA0XzE4MjMyNF8yMDI1MDMyOVQwNzAwMzRfNQ__'
                ]
            ],
            'CE' => [
                'CACTus_(Computer_Aided_CME_Tracking)' => 'all_event_instances',
            ]
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'AR' => true,
            'CE' => true,
        ]);

    }
}
