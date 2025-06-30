<?php
/**
 * Fixes an issue with older client states where baseDiffTime is in the
 * format: Y/m/d H:m:i, the expected format to be consistent with dates
 * across Helioviewer is YYYY-MM-DDTHH:mm:iiZ
 */
require_once sprintf('%s/../../vendor/autoload.php', __DIR__);
require_once sprintf('%s/../config.php', __DIR__);
require_once sprintf('%s/../../src/Database/ClientState.php', __DIR__);

$client_state = new ClientState();
$all_client_states = $client_state->all(100000);

foreach($all_client_states as $cs) {
    $cs_id = $cs['id'];
    $current_state = json_decode($cs['state'], true);

    foreach ($current_state['imageLayers'] as &$layer) {
        if (array_key_exists("baseDiffTime", $layer)) {
            // Parse old date
            $date = DateTime::createFromFormat('Y/m/d H:i:s', $layer['baseDiffTime']);
            // If successful, then rewrite as new date
            if ($date !== false) {
                $layer['baseDiffTime'] = $date->format('Y-m-d\TH:i:s.000\Z');
            }
        }
    }

    $client_state->update($cs_id, $current_state);
}
