<?php 

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventsStateManager;

class EventsStateManagerTest extends TestCase
{
    // Sample events state for testing
    private $eventsState = [
        'tree_HEK' => [
            'id' => 'HEK',
            'markers_visible' => true,
            'labels_visible' => true,
            'layers' => [
                [
                    'event_type' => 'flare',
                    'frms' => ['frm10', 'frm20'],
                    'event_instances' => ['flare--frm1--event1', 'flare--frm2--event2'],
                    'open' => true,
                ]
            ]
        ],
        'tree_CCMC' => [
            'id' => 'CCMC',
            'markers_visible' => true,
            'labels_visible' => false,
            'layers' => [
                [
                    'event_type' => 'storm',
                    'frms' => ['frm30', 'frm40'],
                    'event_instances' => ['storm--frm3--event3', 'storm--frm4--event4'],
                    'open' => true,
                ]
            ]
        ]
    ];


    // Test building EventsStateManager from events state with correct tree
    public function testItShouldBuildFromEventStateArrayAsExpectedWithAllFrms()
    {
        $events_state_with_all = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => false,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['all'],
                        'event_instances' => ['flare--frm1--event1', 'flare--frm2--event2'],
                        'open' => true,
                    ]
                ]
            ],
        ];
        $manager = EventsStateManager::buildFromEventsState($events_state_with_all);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => 'all_frms',
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'flare' => false,
        ]);

    }

    // Test building EventsStateManager from events state with correct tree
    public function testItShouldBuildFromEventStateArrayAsExpected()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => [
                'frm10' => 'all_event_instances',
                'frm20' => 'all_event_instances',
                'frm1' => [
                    'flare--frm1--event1'
                ],
                'frm2' => [
                    'flare--frm2--event2'
                ],
            ],
            'storm' => [
                'frm30' => 'all_event_instances',
                'frm40' => 'all_event_instances',
                'frm3' => [
                    'storm--frm3--event3'
                ],
                'frm4' => [
                    'storm--frm4--event4'
                ],
            ],
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'storm' => false,
            'flare' => true,
        ]);

    }

    // Test building EventsStateManager from events state with correct tree
    public function testItShouldCorrectlyIgnoreLayersWithNotVisibleMarkersAndBuildFromEventStateArrayAsExpected()
    {
        $event_state_markers_not_visible = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => false,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['frm10', 'frm20'],
                        'event_instances' => ['flare--frm1--event1', 'flare--frm2--event2'],
                        'open' => true,
                    ]
                ]
            ],
            'tree_CCMC' => [
                'id' => 'CCMC',
                'markers_visible' => true,
                'labels_visible' => false,
                'layers' => [
                    [
                        'event_type' => 'storm',
                        'frms' => ['frm30', 'frm40'],
                        'event_instances' => ['storm--frm3--event3', 'storm--frm4--event4'],
                        'open' => true,
                    ]
                ]
            ]
        ];
        $manager = EventsStateManager::buildFromEventsState($event_state_markers_not_visible);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'storm' => [
                'frm30' => 'all_event_instances',
                'frm40' => 'all_event_instances',
                'frm3' => [
                    'storm--frm3--event3'
                ],
                'frm4' => [
                    'storm--frm4--event4'
                ],
            ],
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'storm' => false,
        ]);

    }

    public function testItShouldBuildFromInvalidEventStateArrayWithInvalidEventInstancesAsExpected()
    {
        $invalid_event_state = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['frm10', 'frm20'],
                        'event_instances' => ['flare--frm10--event1', 'flare--frm20--event2'],
                        'open' => true,
                    ]
                ]
            ],
            'tree_CCMC' => [
                'id' => 'CCMC',
                'markers_visible' => true,
                'labels_visible' => false,
                'layers' => [
                    [
                        'event_type' => 'storm',
                        'frms' => ['frm30', 'frm40'],
                        'event_instances' => ['storm--frm30--event3', 'storm--frm40--event4'],
                        'open' => true,
                    ]
                ]
            ]
        ];
        $manager = EventsStateManager::buildFromEventsState($invalid_event_state);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => [
                'frm10' => 'all_event_instances',
                'frm20' => 'all_event_instances',
            ],
            'storm' => [
                'frm30' => 'all_event_instances',
                'frm40' => 'all_event_instances',
            ],
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'storm' => false,
            'flare' => true,
        ]);
    }

    // Test building EventsStateManager from legacy event strings
    public function testItShouldBuildFromLegacyEventStrings1()
    {
        $legacyString = '[flare,frm1;frm2,1],[storm,frm3;frm4,0]';
        $manager = EventsStateManager::buildFromLegacyEventStrings($legacyString, true);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => [
                'frm1' => 'all_event_instances',
                'frm2' => 'all_event_instances',
            ],
            'storm' => [
                'frm3' => 'all_event_instances',
                'frm4' => 'all_event_instances',
            ]
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'storm' => true,
            'flare' => true,
        ]);
    }

    public function testItShouldBuildFromLegacyEventStrings2()
    {
        $legacyString = '[flare,frm1,1],[storm,frm3;frm4,0]';
        $manager = EventsStateManager::buildFromLegacyEventStrings($legacyString, false);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => [
                'frm1' => 'all_event_instances',
            ],
            'storm' => [
                'frm3' => 'all_event_instances',
                'frm4' => 'all_event_instances',
            ]
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'storm' => false,
            'flare' => false,
        ]);
    }

    public function testItShouldBuildFromLegacyEventStrings3()
    {
        $legacyString = '[flare,frm1,1],[storm,,0]';
        $manager = EventsStateManager::buildFromLegacyEventStrings($legacyString, false);
        $this->assertInstanceOf(EventsStateManager::class, $manager);
        $this->assertEquals($manager->getStateTree(), [
            'flare' => [
                'frm1' => 'all_event_instances',
            ],
        ]);
        $this->assertEquals($manager->getStateTreeLabelVisibility(), [
            'flare' => false,
        ]);
    }

    // Test if the manager correctly identifies no events
    public function testItShouldCorrectlySayIfNoEvents()
    {
        $emptyState = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => false,
                'labels_visible' => false,
                'layers' => []
            ],
            'tree_CCMC' => [
                'id' => 'CCMC',
                'markers_visible' => false,
                'labels_visible' => false,
                'layers' => []
            ]
        ];
        $manager = EventsStateManager::buildFromEventsState($emptyState);
        $this->assertFalse($manager->hasEvents());
    }

    public function testItShouldCorrectlySayIfNoEventsWithLegacyStrings()
    {
        $legacyString = '[flare,,1],[storm,,0]';
        $manager = EventsStateManager::buildFromLegacyEventStrings($legacyString, false);
        $this->assertFalse($manager->hasEvents());
    }

    // Test checking for events of a specific type
    public function testItShouldTellIfHasEventsForEventType()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertTrue($manager->hasEventsForEventType('flare'));
        $this->assertTrue($manager->hasEventsForEventType('storm'));
    }

    // Test checking for events of an unknown type
    public function testItShouldTellIfHasNoEventsForEventType()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertFalse($manager->hasEventsForEventType('unknown_event_type'));
    }
    
    // Test if the manager applies all events for a specific type
    public function testItShouldTellIfStateAppliesAllEventsForEventType()
    {
        $events_state = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['all'],
                        'event_instances' => [],
                        'open' => true,
                    ]
                ]
            ],
            'tree_CCMC' => [
                'id' => 'CCMC',
                'markers_visible' => true,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'foo',
                        'frms' => ['hede'],
                        'event_instances' => [],
                        'open' => true,
                    ]
                ]
            ]
        ];
        $manager = EventsStateManager::buildFromEventsState($events_state);
        $this->assertTrue($manager->appliesAllEventsForEventType('flare'));
        $this->assertFalse($manager->appliesAllEventsForEventType('foo'));
    }

    
    // Test if a specific frm is applied for an event type
    public function testItShouldAppliesFrmForEventType()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertTrue($manager->appliesFrmForEventType('flare', 'frm1'));
        $this->assertTrue($manager->appliesFrmForEventType('flare', 'frm10'));
        $this->assertFalse($manager->appliesFrmForEventType('flare', 'frm3'));
        $this->assertTrue($manager->appliesFrmForEventType('storm', 'frm3'));
        $this->assertTrue($manager->appliesFrmForEventType('storm', 'frm30'));
        $this->assertFalse($manager->appliesFrmForEventType('storm', 'frm1'));
    }
    
    // Test if a non-existent frm is applied for an event type
    public function testItShouldNotApplyFrmForEventTypeNoFrm()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertFalse($manager->appliesFrmForEventType('flare', 'unknown_frm'));
        $this->assertFalse($manager->appliesFrmForEventType('storm', 'unknown_frm'));
    }

    // Test if all event instances for a frm are applied
    public function testItShouldApplyAllEventInstancesForFrm()
    {
        $eventsState = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['frm1'],
                        'event_instances' => ['flare--frm2--all_event_instances'],
                        'open' => true,
                    ]
                ]
            ]
        ];
        $manager = EventsStateManager::buildFromEventsState($eventsState);
        $this->assertTrue($manager->appliesAllEventInstancesForFrm('flare', 'frm1'));
        $this->assertFalse($manager->appliesAllEventInstancesForFrm('flare', 'frm2'));
    }


    // Test if a specific event instance is applied
    public function testItShouldApplyEventInstanceWhenMatch()
    {
        $event_1 = ['id' => 'event1'];
        $event_id_1 = EventsStateManager::makeEventId('flare', 'frm1', $event_1); 
        $event_2 = ['id' => 'event2'];
        $event_id_2 = EventsStateManager::makeEventId('flare', 'frm2', $event_2); 

        $event_3 = ['id' => 'event3'];
        $event_id_3 = EventsStateManager::makeEventId('storm', 'frm3', $event_3); 
        $event_4 = ['id' => 'event4'];
        $event_id_4 = EventsStateManager::makeEventId('storm', 'frm4', $event_4); 

        $event_5 = ['id' => 'event5'];
        $event_id_5 = EventsStateManager::makeEventId('storm', 'frm4', $event_5); 

        $event_state = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => true,
                'layers' => [
                    [
                        'event_type' => 'flare',
                        'frms' => ['frm10', 'frm20'],
                        'event_instances' => [$event_id_1, $event_id_2],
                        'open' => true,
                    ]
                ]
            ],
            'tree_CCMC' => [
                'id' => 'CCMC',
                'markers_visible' => true,
                'labels_visible' => false,
                'layers' => [
                    [
                        'event_type' => 'storm',
                        'frms' => ['frm30', 'frm40'],
                        'event_instances' => [$event_id_3, $event_id_4],
                        'open' => true,
                    ]
                ]
            ]
        ];

        $manager = EventsStateManager::buildFromEventsState($event_state);
        $this->assertTrue($manager->appliesEventInstance('flare', 'frm1', $event_1));
        $this->assertTrue($manager->appliesEventInstance('flare', 'frm2', $event_2));
        $this->assertTrue($manager->appliesEventInstance('storm', 'frm3', $event_3));
        $this->assertTrue($manager->appliesEventInstance('storm', 'frm4', $event_4));
        $this->assertFalse($manager->appliesEventInstance('storm', 'frm3', $event_5));
    }

    // Test if a non-existent event instance is applied
    public function testItShouldNotApplyEventInstanceNoInstance()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $event = ['id' => 'unknown_event'];
        $this->assertFalse($manager->appliesEventInstance('flare', 'frm1', $event));
    }

    // Test if event type labels are visible
    public function testItShouldCorrectlyKeepEventTypesLabelVisible()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertTrue($manager->isEventTypeLabelVisible('flare'));
        $this->assertFalse($manager->isEventTypeLabelVisible('storm'));
    }

    // Test if event type labels are visible for an unknown event type
    public function testItShouldCorrectlyReportNonExistantEventTypesLabelVisible()
    {
        $manager = EventsStateManager::buildFromEventsState($this->eventsState);
        $this->assertFalse($manager->isEventTypeLabelVisible('unknown_event_type'));
    }
}

