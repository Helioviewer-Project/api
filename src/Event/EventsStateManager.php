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

use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Sentry\Sentry;

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

            // Skip only when markers_visible is explicitly set to a non-truthy
            // value. Missing key is treated as "on" by historical convention.
            if (array_key_exists('markers_visible', $eventHelioGroupState) && $eventHelioGroupState['markers_visible'] != true) {
                continue;
            }


            foreach(($eventHelioGroupState['layers'] ?? []) as $eventHelioGroupLayer) {

                $layer_event_type = $eventHelioGroupLayer['event_type'] ?? null;
                if ($layer_event_type === null) {
                    continue;
                }

                if (!array_key_exists($layer_event_type, $this->events_tree)) {
                    $this->events_tree[$layer_event_type] = [];
                    $this->events_tree_label_visibility[$layer_event_type] = $eventHelioGroupState['labels_visible'] ?? false;
                }

                $layerFrms = $eventHelioGroupLayer['frms'] ?? [];

                // This damn all fix
                if (in_array("all", $layerFrms)) {
                    $this->events_tree[$layer_event_type] = "all_frms";
                } else {

                    foreach($layerFrms as $eventLayerFrm) {
                        $event_layer_frm = str_replace('\\', '', $eventLayerFrm);
                        if (!array_key_exists($event_layer_frm, $this->events_tree[$layer_event_type])) {
                            $this->events_tree[$layer_event_type][$event_layer_frm] = 'all_event_instances';
                        }
                    }

                    foreach(($eventHelioGroupLayer['event_instances'] ?? []) as $eventLayerEventInstance) {

                        $event_instance_frm_pieces = explode('--',$eventLayerEventInstance);
                        $event_instance_frm = $event_instance_frm_pieces[1] ?? null;
                        if ($event_instance_frm === null) {
                            // Malformed instance id (missing "--<frm>--" segment); skip
                            continue;
                        }

                        $event_instance_frm = str_replace('\\', '', $event_instance_frm);

                        // if we have frms all included like "frm1" and in event instance "flare--frm1--event1"
                        // we just ignore those since they are all included into the tree with frm1 anyways
                        // this is also indicates, eventsState is invalid somehow
                        if (in_array($event_instance_frm, $layerFrms)) {
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
     * Get the source names (HEK, CCMC, RHESSI) that have events enabled
     * @return string[]
     */
    public function getSources(): array
    {
        return array_map(
            fn($key) => str_replace('tree_', '', $key),
            array_keys($this->events_state)
        );
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
     * Build path-prefix selection strings for the events API
     * frames_with_selections endpoint.
     *
     * ─── End-to-end example ───────────────────────────────────────────────
     *
     * INPUT  $this->events_state:
     *   [
     *     'tree_HEK' => [
     *       'markers_visible' => true,
     *       'layers' => [
     *         ['event_type' => 'AR', 'frms' => ['all'],        'event_instances' => []],
     *         ['event_type' => 'FL', 'frms' => ['NOAA_SWPC'], 'event_instances' => ['flare--SDO HMI--evt-123']],
     *       ],
     *     ],
     *     'tree_CCMC' => [
     *       'markers_visible' => true,
     *       'layers_v2' => ['CCMC>>DONKI>>CME'],
     *     ],
     *     'tree_RHESSI' => [
     *       'markers_visible' => false,   // <- muted, skipped entirely
     *       'layers' => [...],
     *     ],
     *   ]
     *
     * OUTPUT (after dedup):
     *   [
     *     'HEK>>Active Region',          // AR with frms=['all'] -> level 1
     *     'HEK>>Flare>>NOAA_SWPC',       // FL + 'NOAA_SWPC'
     *     'HEK>>Flare>>NOAA SWPC',       //   variant: underscores -> spaces
     *     'HEK>>Flare>>SDO HMI',         // FRM parsed out of event_instance
     *     'HEK>>Flare>>SDO_HMI',         //   variant: spaces -> underscores
     *     'CCMC>>DONKI>>CME',            // layers_v2 passed straight through
     *   ]
     *
     * Why three variants for FRM names?
     *   FRM strings come from user/frontend input and the upstream events API
     *   has historically been inconsistent about whether spaces or underscores
     *   are canonical. Emitting raw + both transforms means whichever form the
     *   upstream stores, at least one of our paths will prefix-match it.
     *
     * @return string[]  deduplicated list of path-prefix selection strings
     */
    public function getSelections(): array
    {
        $selections = [];

        // Drive the loop off the canonical source list (EventsApi::VALID_SOURCES)
        // rather than whatever happens to be in events_state. Typo'd entries
        // like "tree_HEKL" are silently ignored; iteration order is stable.
        foreach (EventsApi::VALID_SOURCES as $source) {

            $treeKey = 'tree_' . $source;
            if (!isset($this->events_state[$treeKey])) {
                continue;
            }
            $sourceState = $this->events_state[$treeKey];

            // Muted source contributes nothing. Mirrors the constructor's
            // historical convention: only skip when markers_visible is
            // EXPLICITLY non-truthy; absent key falls through.
            //   e.g. tree_RHESSI with markers_visible=false  =>  skip
            if (array_key_exists('markers_visible', $sourceState) && $sourceState['markers_visible'] != true) {
                continue;
            }

            // ─── Shortcut: layers_v2 ────────────────────────────────────────
            // If the frontend has already produced canonical selection paths
            // (e.g. ['CCMC>>DONKI>>CME']), pass them through verbatim. No pin
            // lookup, no FRM variants — the frontend owns that.
            if (!empty($sourceState['layers_v2'])) {
                foreach ($sourceState['layers_v2'] as $path) {
                    $selections[] = $path;
                }
                continue;
            }

            // ─── Walk layers (legacy / v1 path) ─────────────────────────────
            // Each layer entry describes one event_type within this source.
            //   layer = ['event_type'=>'AR', 'frms'=>['all'], 'event_instances'=>[]]
            foreach ($sourceState['layers'] ?? [] as $layer) {

                $pin = $layer['event_type'] ?? null;
                if ($pin === null) {
                    // Malformed layer: no event_type field.
                    Sentry::setContext('getSelections', [
                        'source'       => $source,
                        'layer'        => $layer,
                        'events_state' => $this->events_state, // original payload, for debugging
                    ]);
                    Sentry::message("getSelections: layer missing 'event_type'");
                    continue;
                }

                // Translate the 2-3 letter pin into the upstream API's
                // human-readable label.
                //   ('HEK', 'AR') -> 'Active Region'
                //   ('HEK', 'FL') -> 'Flare'
                //   ('CCMC', 'FP') -> 'Solar Flare Predictions'
                //   Unknown pin -> null -> skip (matches EventSelections behavior)
                $label = EventSelections::$event_types_map[$source][$pin] ?? null;
                if ($label === null) {
                    // Pin isn't in the map - usually means a typo or a new
                    // event type we haven't registered yet. Visible signal,
                    // except for 'UNK' which is intentionally out of the map
                    // (it's the frontend's "unknown / fallback" sentinel).
                    if ($pin !== 'UNK') {
                        Sentry::setContext('getSelections', [
                            'source'       => $source,
                            'pin'          => $pin,
                            'events_state' => $this->events_state, // original payload, for debugging
                        ]);
                        Sentry::message("getSelections: unknown pin '{$pin}' for source '{$source}'");
                    }
                    continue;
                }

                $frms           = $layer['frms']            ?? [];   // e.g. ['NOAA_SWPC'] or ['all']
                $eventInstances = $layer['event_instances'] ?? [];   // e.g. ['flare--SDO HMI--evt-123']

                // ─── Level 1: 'all' wildcard ────────────────────────────────
                // frms=['all'] means "include every FRM under this event_type".
                // We can emit a single 2-part path; the upstream prefix matcher
                // will catch every event regardless of FRM.
                //   ('HEK', 'Active Region')  =>  "HEK>>Active Region"
                if (in_array('all', $frms, true)) {
                    $selections[] = "{$source}>>{$label}";
                    continue;
                }

                // Helper: emit a FRM-deep path plus two whitespace variants.
                // FRM strings come from user input, so we hedge against
                // upstream naming drift (space vs underscore).
                //   $frm = "NOAA_SWPC"  =>
                //     "HEK>>Flare>>NOAA_SWPC"   (raw)
                //     "HEK>>Flare>>NOAA_SWPC"   (spaces->underscores: no change)
                //     "HEK>>Flare>>NOAA SWPC"   (underscores->spaces)
                $pushFrmVariants = function (string $frm) use (&$selections, $source, $label) {
                    $selections[] = "{$source}>>{$label}>>{$frm}";
                    $selections[] = "{$source}>>{$label}>>" . str_replace(' ', '_', $frm);
                    $selections[] = "{$source}>>{$label}>>" . str_replace('_', ' ', $frm);
                };

                // ─── Level 2: explicit FRM list ─────────────────────────────
                // frms=['NOAA_SWPC','SPoCA']  =>  one path-variants-set per FRM
                foreach ($frms as $frm) {
                    $pushFrmVariants($frm);
                }

                // ─── Level 3: FRMs only referenced via event_instances ──────
                // The frontend can target a single event by listing an entry
                // in event_instances without including that event's FRM in
                // the `frms` array. We still need to fetch at the FRM level
                // (upstream matches by path prefix, not by event id) and
                // filter to the specific instance later in the renderer.
                //
                //   event_instance = "flare--SDO HMI--evt-123"
                //   parts          = ['flare', 'SDO HMI', 'evt-123']
                //   frm            = 'SDO HMI'   (parts[1])
                //   -> only push if SDO HMI isn't already in $frms (no dup work)
                foreach ($eventInstances as $ei) {
                    $parts = explode('--', $ei);
                    $frm = $parts[1] ?? null;
                    if ($frm === null) {
                        // event_instance string didn't carry a FRM segment.
                        // Expected shape: "<type>--<frm>--<id>".
                        Sentry::setContext('getSelections', [
                            'source'         => $source,
                            'pin'            => $pin,
                            'event_instance' => $ei,
                            'events_state'   => $this->events_state, // original payload, for debugging
                        ]);
                        Sentry::message("getSelections: malformed event_instance (no FRM segment)");
                        continue;
                    }
                    if (!in_array($frm, $frms, true)) {
                        $pushFrmVariants($frm);
                    }
                }
            }
        }

        // Dedup: same FRM in multiple layers, or variant collisions where the
        // raw FRM already has the canonical form (e.g. "NOAA_SWPC" produces
        // an identical raw and spaces->underscores result).
        return array_values(array_unique($selections));
    }

    /**
     * Build per-source visibility config for use by EventContext / renderer.
     *
     * Returns one entry per source in EventsApi::VALID_SOURCES (so callers
     * always get a complete map). Missing source in events_state OR missing
     * 'labels_visible' key both default to TRUE (labels are visible by
     * default; the frontend must explicitly opt out).
     *
     * Shape:
     *   [
     *     'HEK'    => ['label_visibility' => true],
     *     'CCMC'   => ['label_visibility' => true],
     *     'RHESSI' => ['label_visibility' => true],
     *   ]
     *
     * Nested dict (one key today, room for more like marker_visibility
     * later without breaking callers).
     *
     * @return array<string, array{label_visibility: bool}>
     */
    public function getVisibilitySelections(): array
    {
        $result = [];
        foreach (EventsApi::VALID_SOURCES as $source) {
            $treeKey = 'tree_' . $source;
            // Default to true: labels are visible unless the frontend explicitly opts out.
            $labelVisible = $this->events_state[$treeKey]['labels_visible'] ?? true;
            $result[$source] = ['label_visibility' => (bool) $labelVisible];
        }
        return $result;
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
