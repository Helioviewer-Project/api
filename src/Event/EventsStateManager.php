<?php
/**
 * EventsStateManager Class Definition
 * A helper class to parse old event strings and posted events state from frontend
 *
 * @category Event
 * @package  Helioviewer
 * @author   Kasim Necdet Percinel <kasim.n.oercinel@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Event;

class EventsStateManager 
{
    // internal events state original
    public array $events_state; 

    // internal structure to process random events, if they are OK to be in above events_state
    private array $events_tree; 

    // internal structure to process random events, if their labels are visible
    private array $events_tree_label_visibility; 

    /**
     * Creates a new EventsStateManager
     * @param  array $events_state, events state posted from frontend
     * @return void
     */
    private function __construct(array $events_state) 
    {
        $this->events_state = $events_state;
        $this->events_tree = []; 
        $this->events_tree_label_visibility = [];

        foreach($events_state as $eventHelioGroupName => $eventHelioGroupState) { // CCMC or HEK state

            // If  we don't have visible markers for CCMC or HEK then no need to handle them, that is easy
            if ($eventHelioGroupState['markers_visible']) {

                foreach($eventHelioGroupState['layers'] as $eventHelioGroupLayer) {

                    $layer_event_type = $eventHelioGroupLayer['event_type'];
                
                    if (!array_key_exists($layer_event_type, $this->events_tree)) {
                        $this->events_tree[$layer_event_type] = [];
                        $this->events_tree_label_visibility[$layer_event_type] = $eventHelioGroupState['labels_visible'];
                    }

                    // This damn all fix
                    if (in_array("all",$eventHelioGroupLayer['frms'])) {
                        $this->events_tree[$layer_event_type] = "all_frms";
                    } else {

                        foreach($eventHelioGroupLayer['frms'] as $eventLayerFrm) {
                            if (!array_key_exists($eventLayerFrm, $this->events_tree[$layer_event_type])) {
                                $this->events_tree[$layer_event_type][$eventLayerFrm] = 'all_event_instances';
                            }
                        }

                        foreach($eventHelioGroupLayer['event_instances'] as $eventLayerEventInstance) {

                            $event_instance_frm_pieces = explode('--',$eventLayerEventInstance);
                            $event_instance_frm = $event_instance_frm_pieces[1];

                            // if we have frms all included like "frm1" and in event instance "flare--frm1--event1" 
                            // we just ignore those since they are all included into the tree with frm1 anyways
                            // this is also indicates, eventsState is invalid somehow   
                            if (in_array($event_instance_frm, $eventHelioGroupLayer['frms'])) {
                                continue;
                            }
                            
                            if (!array_key_exists($event_instance_frm, $this->events_tree[$layer_event_type])) {
                                $this->events_tree[$layer_event_type][$event_instance_frm] = [];
                            }

                            $this->events_tree[$layer_event_type][$event_instance_frm][] = $eventLayerEventInstance;
                        }
                    }
                    
                }
            }
        }

    }

    /**
     * Creates a new EventsStateManager from events_state
     * @param  array $events_state, events state posted from frontend
     * @return EventsStateManager
     */
    public static function buildFromEventsState(array $events_state) : EventsStateManager 
    {
        return new self($events_state);
    }

    /**
     * Creates a new EventsStateManager from events_state
     * @param  array $events_state, events state posted from frontend
     * @return EventsStateManager
     */
    public static function buildFromLegacyEventStrings(string $events_state_string, bool $events_label) : EventsStateManager 
    {
        $events_layers = [];
        
        // Prevent possible bugs
        $events_state_string = trim($events_state_string);

        // this is  one of the bloody cases
        if(!empty($events_state_string)) {
            $event_strings = explode("],[", trim(stripslashes($events_state_string), ']['));

            // Process individual events in string
            foreach ($event_strings as $es) {

                $event_pieces = explode(",", $es);

                // just don't take risks in this environment
                // there should be 3 element 
                if (count($event_pieces) < 3) {
                    continue; 
                }

                list($event_type, $combined_frms, $visible) = $event_pieces;

                $frms = explode(";", $combined_frms);
                if (!empty($combined_frms) && !empty($frms)) {
                    // if frms not empty
                    if (!empty($frms)) {
                        // if this event type defined earlier
                        if(array_key_exists($event_type, $events_layers)) {
                           $event_layers[$event_type]['frms'] = array_unique(array_merge($events_layers[$event_type]['frms'], $frms)); 
                        } else {
                            $events_layers[$event_type] = [
                                'event_type' => $event_type,
                                'frms' => $frms,
                                'event_instances' => [],
                                'open' => true, //  do not know this fields function better to keep it
                            ];
                        }
                    }
                }
            }
        }

        $events_state = [
            'tree_HEK' => [
                'id' => 'HEK',
                'markers_visible' => true,
                'labels_visible' => $events_label, 
                'layers' => array_values($events_layers),
            ]
        ];

        return new self($events_state);
    }


    /**
     * Export events state to stream into database tables
     * @return string
     */
    public function export() : string 
    {
        return json_encode($this->events_state);
    }
    
    /**
     * Tells if there is events in this manager
     * @return bool
     */
    public function hasEvents() : bool 
    {
        return count($this->events_tree) > 0;
    }

    /**
     * Lets you to access to events_state 
     * @return array
     */
    public function getState(): array
    {
        return $this->events_state;
    }

    /**
     * Lets you to access to events_tree
     * @return array
     */
    public function getStateTree(): array
    {
        return $this->events_tree;
    }

    /**
     * Lets you to access to events_tree_label_visibility
     * @return array
     */
    public function getStateTreeLabelVisibility(): array
    {
        return $this->events_tree_label_visibility;
    }

    /**
     * Checks if this event_category has events in this state
     * @param  string event_category_pin , given event_type
     * @return bool
     */
    public function hasEventsForEventType(string $event_category_pin): bool
    {
        return array_key_exists($event_category_pin, $this->events_tree);
    }

    /**
     * Checks if this event state allows all events for this event_type
     * @param  string event_category_pin , given event_type
     * @return bool
     */
    public function appliesAllEventsForEventType(string $event_category_pin): bool
    {
        return $this->hasEventsForEventType($event_category_pin) && $this->events_tree[$event_category_pin] == "all_frms";
    }

    /**
     * Checks if this event_category and frm_name has events in this state
     * @param  string event_category_pin , given event_type
     * @param  string frm_name , given frm_name
     * @return bool
     */
    public function appliesFrmForEventType(string $event_category_pin, string $frm_name): bool 
    {
        // We keep IDs with underscores to reduce bugs
        $frm_underscored_name = str_replace(' ', '_', $frm_name);

        // Check if we want the events for this group
        return in_array($frm_underscored_name, array_keys($this->events_tree[$event_category_pin]));
    } 

    /**
     * Checks if this event state allows all events for frm of this event_type
     * @param  string event_category_pin , given event_type
     * @param  string frm_name , given frm_name
     * @return bool
     */
    public function appliesAllEventInstancesForFrm(string $event_category_pin, string $frm_name): bool 
    {
        // We keep IDs with underscores to reduce bugs
        $frm_underscored_name = str_replace(' ', '_', $frm_name);

        // All event instances works for this frm
        $all_event_instances_work_for_frm = $this->events_tree[$event_category_pin][$frm_underscored_name] == "all_event_instances";

        return $this->appliesFrmForEventType($event_category_pin, $frm_name) && $all_event_instances_work_for_frm;
    }


    /**
     * Checks if this event state allows this particular event_instance 
     * @param  string event_category_pin , given event_type
     * @param  string frm_name , given frm_name
     * @param  array event , given event to check 
     * @return bool
     */
    public function appliesEventInstance(string $event_category_pin, string $frm_name, array $event): bool 
    {
        $event_instance_id = self::makeEventId($event_category_pin, $frm_name, $event);

        // We keep IDs with underscores to reduce bugs
        $frm_underscored_name = str_replace(' ', '_', $frm_name);

        return in_array($event_instance_id, $this->events_tree[$event_category_pin][$frm_underscored_name]);
    }

    /**
     * Checks if this event state allows events labels are visiblle for given event_category
     * @param  string event_category_pin , given event_type
     * @return bool
     */
    public function isEventTypeLabelVisible(string $event_category_pin): bool 
    {
        $is_defined_visiblity =  array_key_exists($event_category_pin, $this->events_tree_label_visibility);  
        return $is_defined_visiblity && $this->events_tree_label_visibility[$event_category_pin];
    }


    /**
     * Makes event id from given event and its belonging event_type and frm_name
     * @param  string event_category_pin , given event_type
     * @param  string frm_name , given frm_name
     * @param  array event , given event to check 
     * @return string
     */
    public static function makeEventId(string $event_category_pin, string $frm_name, array $event): string 
    {
        $event_id_pieces = [
            $event_category_pin, 
            $frm_name, 
            base64_encode($event['id']),
        ]; 

        $cleaned_event_id_pieces = array_map(function($p) {
            return str_replace([' ','=','+','.','(',')'], ['_','_','\+','\.','\(','\)'], $p); 
        }, $event_id_pieces);

        return join('--', $cleaned_event_id_pieces); 
    }

}

?>
