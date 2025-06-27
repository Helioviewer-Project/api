<?php
require_once sprintf('%s/../../vendor/autoload.php', __DIR__);
require_once sprintf('%s/../config.php', __DIR__);
require_once sprintf('%s/../../pre.php', __DIR__);
require_once sprintf('%s/../../src/Database/ClientState.php', __DIR__);

use Helioviewer\Api\Event\EventsStateManager;


function pullEvents($timestamp, $source) 
{
    $cacheDir = '/tmp';

    $url = sprintf('https://api.helioviewer.org?startTime=%s&action=events&sources=%s', urlencode(gmdate("Y-m-d\TH:i:s.000\Z", substr($timestamp, 0, -3))), $source);

    // Create a unique cache key based on method + URL + options hash
    $cacheKey = md5($url);
    $cacheFile = "$cacheDir/{$cacheKey}.cache";

    // Check if cache is valid
    if (file_exists($cacheFile)) {
        $cached_body = file_get_contents($cacheFile);
        // Get the response body and decode the JSON into an associative array
        $cached_data = json_decode($cached_body, true);

        return $cached_data;
    }

	sleep(1);
    $client = new \GuzzleHttp\Client();

    try {
        // Make the GET request to your desired URL
        $response = $client->request('GET', $url);
		$body = (string)$response->getBody();

		// Send request and cache response
		file_put_contents($cacheFile, $body);

        // Get the response body and decode the JSON into an associative array
        $data = json_decode($body, true);

        return $data;
    } catch (\GuzzleHttp\Exception\RequestException $e) {
        // Handle request exceptions (network errors, 4xx and 5xx responses)
        throw $e;
    }
}

function flatEvents($api_events) 
{
    $usable_events = [];

    foreach($api_events as $event_type) {
        foreach($event_type['groups'] as $frm) {
            foreach($frm['data'] as $e) {
                $event_id = EventsStateManager::makeEventId($event_type['pin'], $frm['name'], $e);
            
                if (isset($e['short_label']) && !empty($e['short_label'])) {
                    $usable_events[$event_id] = $e['short_label'];
                } else {
                    $usable_events[$event_id] = $e['label'];
                }
            }
        }
    }

    return $usable_events;
}

function structEvents($api_events) 
{
    $tree = [];

    foreach($api_events as $event_type) {

        $event_type_name = $event_type['name'];

        foreach($event_type['groups'] as $frm) {
            
            $frm_name = $frm['name'];

            foreach($frm['data'] as $e) {
                if (isset($e['short_label']) && !empty($e['short_label'])) {
                    $tree[$event_type_name][$frm_name][] = $e['short_label'];
                } else {
                    $tree[$event_type_name][$frm_name][] = $e['label'];
                }
            }
        }
    }

    return $tree;

}

function undoEscapeInvalidCssChars($selector) {
    $selector = str_replace("_", " ", $selector);                
    $selector = str_replace('\\',"", $selector);
    return $selector;
}

function escapeInvalidCssChars($selector) {
    $selector = str_replace(" ", "_", $selector);                
    $selector = str_replace("=", "_", $selector);                
    $selector = preg_replace("/([\+\.\(\)])/","\\$1", $selector);    
    return $selector;
}

function makeEventInstanceTreeNodeID($eventPin, $frmName, $eventID) {
      $escapedFrmName = escapeInvalidCssChars($frmName);
      $encodedEventID = escapeInvalidCssChars(base64_encode($eventID));
      return $eventPin . "--" . $escapedFrmName . "--" . $encodedEventID;
}


function makeLayersV2($current_layers, $timestamp, $source, $client_state_id) 
{
    // pre($current_layers);
    $api_events = pullEvents($timestamp, $source);
    $api_events_tree = structEvents($api_events);
    $flat_events = flatEvents($api_events);

    $event_type_labels = [
        'HEK' => [
            'AR' => 'Active Region',  
            'CC' => 'Coronal Cavity',  
            'CD' => 'Coronal Dimming',  
            'CH' => 'Coronal Hole',  
            'CJ' => 'Coronal Jet',  
            'CE' => 'CME',  
            'CR' => 'Coronal Rain',  
            'CW' => 'Coronal Wave',  
            'EF' => 'Emerging Flux',  
            'ER' => 'Eruption',  
            'FI' => 'Filament',  
            'FA' => 'Filament Activation',  
            'FE' => 'Filament Eruption',  
            'FL' => 'Flare',  
            'LP' => 'Loop',  
			'OT' => 'Other',
			'NR' => 'NothingReported',
            'OS' => 'Oscillation',  
            'PG' => 'Plage',  
			'PT' => 'PeacockTail',
			'PB' => 'ProminenceBubble',
            'SG' => 'Sigmoid',  
            'SP' => 'Spray Surge',  
            'SS' => 'Sunspot',
			'TO' => 'Topological Object',
			'BU' => 'UVBurst',
			'HY' => 'Hypothesis',
			'EE' => 'ExplosiveEvent',
			'UNK' => 'Unknown',
        ],
        'CCMC' => [
            'C3' => 'DONKI',
            'FP' => 'Solar Flare Predictions',
        ],
        'RHESSI' => [
            'F2' => 'Solar Flares',
        ],
    ];

    $new_layers = [];

    foreach($current_layers as $cl) {

        $event_type_label = $event_type_labels[$source][$cl['event_type']];

        if(count($cl['frms']) == 1 && $cl['frms'][0] == 'all') {
            $new_layers[] = sprintf("%s>>%s", $source, $event_type_label);
            continue;
        }

        if (!array_key_exists($event_type_label, $api_events_tree)) {
            echo sprintf("%s could not be found for id:%s\n", $event_type_label, $client_state_id);
            // $new_layers[] = sprintf("%s>>%s", $source, $event_type_label);
            continue;
        }

        $all_frms_for_event_type = array_keys($api_events_tree[$event_type_label]);

        if(!empty($cl['frms'])) {

            foreach($cl['frms'] as $frm) {

                $cleaned_frm = str_replace(["\\+", "\\(", "\\)", "\\."], ["+", "(", ")", "."], $frm);
                $underscore_cleaned_frm = str_replace('_', ' ', $cleaned_frm);
                
                // echo sprintf("'%s' '%s' '%s'\n", $frm, $cleaned_frm, $underscode_cleaned_frm);

                if(in_array($cleaned_frm, $all_frms_for_event_type)) {
                    $new_layers[] = sprintf("%s>>%s>>%s", $source, $event_type_label, $cleaned_frm);
                } else if(in_array($underscore_cleaned_frm, $all_frms_for_event_type)) {
                    $new_layers[] = sprintf("%s>>%s>>%s", $source, $event_type_label, $underscore_cleaned_frm);
                } 
            }
        }

        foreach($cl['event_instances'] as $ei) {

            $all_frms_for_event_type = array_keys($api_events_tree[$event_type_label]);

            list($ei_event_type_pin, $ei_frm, $ei_base64) = explode('--', $ei);

            $ei_event_type_label = $event_type_labels[$source][$ei_event_type_pin];
            $all_frms_for_ei_event_type = array_keys($api_events_tree[$ei_event_type_label]);

            $ei_cleaned_frm = str_replace(["\\+", "\\(", "\\)", "\\."], ["+", "(", ")", "."], $ei_frm);
            $ei_underscore_cleaned_frm = str_replace('_', ' ', $ei_cleaned_frm);

            $ei_frm_selection = "";

            if(in_array($ei_cleaned_frm, $all_frms_for_ei_event_type)) {
                $ei_frm_selection = sprintf("%s>>%s>>%s", $source, $ei_event_type_label, $ei_cleaned_frm);
            } else if(in_array($ei_underscore_cleaned_frm, $all_frms_for_ei_event_type)) {
                $ei_frm_selection = sprintf("%s>>%s>>%s", $source, $ei_event_type_label, $ei_underscore_cleaned_frm);
            } 

            if(!array_key_exists($ei, $flat_events)) {
                throw new \Exception($ei . " couldn't be found in api_events for date:". gmdate("Y-m-d\TH:i:s.000\Z", substr($timestamp, 0, -3)));
            }

            $event_label = $flat_events[$ei];

            $new_layers[] = sprintf("%s>>%s", $ei_frm_selection, $event_label);
        }

    }

    return $new_layers;

}

$client_state = new ClientState();

$all_client_states = $client_state->all(100000);

$load_state_url = "https://gs671-suske.ndc.nasa.gov/?loadState=%s";

foreach($all_client_states as $cs) {
	
    $cs_id = $cs['id'];

    $current_state = json_decode($cs['state'], true);
    $current_state_date = $current_state['date'];

    foreach($current_state['eventLayers'] as $tree_id => $tree_layer) {

        $source = $tree_layer['id'];
        $source_layers = $tree_layer['layers'];

        $current_state['eventLayers'][$tree_id]['layers_v2'] = [];

        if(!empty($source_layers)) {
            $current_state['eventLayers'][$tree_id]['layers_v2'] = makeLayersV2($source_layers, $current_state_date, $source, $cs_id);
        }
    }

    $client_state->update($cs_id, $current_state);

}

