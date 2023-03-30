<?php declare(strict_types=1);

/**
 * Helioviewer has a strict data format when it comes to features & events.
 * This class helps to normalize the HEK data into that format.
 */
class Event_HEKEventNormalizer
{
    /**
     * Takes HEK Feature recognition methods and events, and normalizes them into the Helioviewer Event Format
     */
    static public function Normalize(array &$event_types, array &$events): array {
        // Normalizes event FRMs into the main data container.
        // FRMs make up everything in the Helioviewer Event Format except for the data itself.
        $event_container = self::NormalizeFRMs($event_types);
        foreach ($events as &$event) {
            // Operates in-place to assign eve
            $event['hv_hpc_x'] = $event['hv_hpc_x_final'];
            $event['hv_hpc_y'] = $event['hv_hpc_y_final'];
            $event['label'] = self::CreateEventLabel($event);
            $event['version'] = $event['frm_specificid'];
            $event['id'] = $event['kb_archivid'];
            $event['type'] = $event['event_type'];
            $event['start'] = $event['event_starttime'];
            $event['end'] = $event['event_endtime'];
            self::AssignEventToFRM($event_container, $event);
        }
        return $event_container;
    }

    static private function CreateEventLabel(array &$event): string {
        $out = "";
        foreach ($event['hv_labels_formatted'] as $_ => $label) {
            $out .= $label . "\n";
        }
        return $out;
    }

    /**
     * Convert legacy FRM data into the Helioviewer Event Format
     * legacy data typically comes from Helioviewer's geEventFRMs API call.
     */
    static public function NormalizeFRMs(array &$event_types): array {
        $result = [];
        // The event types is not a list, but rather an object where each key is the
        foreach ($event_types as $event_type => &$event_frms) {
            // The FRM name has the pin type and actual name sp
            $split = explode("/", $event_type);
            $label = $split[0];
            $type = $split[1];

            $event = [
                'name' => $label,
                'pin' => $type,
                'groups' => []
            ];
            foreach($event_frms as $frm_name => &$frm_details) {
                $normal_frm = [
                    'name' => $frm_name,
                    'contact' => $frm_details['frm_contact'],
                    'url' => $frm_details['frm_url'],
                    'data' => []
                ];
                array_push($event['groups'], $normal_frm);
            }

            array_push($result, $event);
        }
        return $result;
    }

    static private function AssignEventToFRM(array &$container, array &$event) {
        // Look for the matching FRM in the normalized FRM list, this can most likely be optimized if it's a problem, but normal_frms is small.
        $frm = self::PushEventToFRM($container, $event['concept'], $event['frm_name'], $event);
    }

    /**
     * Searches the frms array for the matching event type and frm name.
     * Returns an editable reference to the FRM
     */
    static private function PushEventToFRM(array &$container, $event_type, $frm_name, $event) {
        foreach ($container as &$event_container) {
            if ($event_container['name'] == $event_type) {
                foreach ($event_container['groups'] as &$frm) {
                    if ($frm['name'] == $frm_name) {
                        array_push($frm['data'], $event);
                        return;
                    }
                }
            }
        }
    }
}
