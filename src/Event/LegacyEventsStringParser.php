<?php declare(strict_types=1);
/**
 * LegacyEventsStringParser
 *
 * Parses the legacy URL bracket-string event format used by the pre-JSON
 * takeScreenshot / queueMovie endpoints, e.g.
 *
 *     [AR,all,1],[FL,NOAA_SWPC;SDO_HMI,1],[CH,SPoCA,0]
 *
 * into canonical "SOURCE>>Label[>>FRM]" selection paths, resolving each
 * event-type code against EventTypeCatalogue::MAP.
 *
 * Parsing logic lifted verbatim from the former
 * EventSelections::buildFromLegacyEventStrings so it survives that class's
 * retirement. The per-triple visibility flag (the trailing 1/0) is ignored,
 * matching the original behavior -- every listed event is treated as selected.
 *
 * @category Event
 * @package  Helioviewer
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

namespace Helioviewer\Api\Event;

final class LegacyEventsStringParser
{
    /**
     * Parse a legacy bracket-string into canonical selection paths.
     *
     * @param string $events_state_string e.g. "[AR,all,1],[FL,NOAA_SWPC,1]"
     * @return string[] canonical "SOURCE>>Label[>>FRM]" paths
     */
    public static function parse(string $events_state_string): array
    {
        $selections = [];

        // Prevent possible bugs
        $events_state_string = trim($events_state_string);

        if (!empty($events_state_string)) {
            $stripped = stripslashes($events_state_string);
            // Remove only the outermost [ and ]
            if (str_starts_with($stripped, '[') && str_ends_with($stripped, ']')) {
                $stripped = substr($stripped, 1, -1);
            }
            $event_strings = explode("],[", $stripped);

            // Process individual events in string
            foreach ($event_strings as $es) {

                $event_pieces = explode(",", $es);

                // there should be 3 elements
                if (count($event_pieces) < 3) {
                    continue;
                }

                list($event_type, $combined_frms, $visible) = $event_pieces;

                // Find the source (HEK, CCMC, RHESSI, WSA) and label for this event_type
                $source = null;
                $label = null;
                foreach (EventTypeCatalogue::MAP as $src => $types) {
                    if (array_key_exists($event_type, $types)) {
                        $source = $src;
                        $label = $types[$event_type];
                        break;
                    }
                }

                // Skip if event_type not found in map
                if ($source === null || $label === null) {
                    continue;
                }

                $frms = explode(";", $combined_frms);

                // If 'all' or empty frms, just use SOURCE>>LABEL
                if (empty($combined_frms) || $combined_frms === 'all' || in_array('all', $frms)) {
                    $selections[] = $source . '>>' . $label;
                } else {
                    // For each specific FRM, create SOURCE>>LABEL>>FRM
                    foreach ($frms as $frm) {
                        $frm = trim($frm);
                        if (!empty($frm)) {
                            $selections[] = $source . '>>' . $label . '>>' . $frm;
                        }
                    }
                }
            }
        }

        return $selections;
    }

    /**
     * Convenience for the URL-params screenshot/movie endpoints: parse the
     * ?events= string into selection paths and pair it with a per-source
     * visibility map derived from the global ?eventLabels= flag.
     *
     * The legacy URL format has no per-source marker toggle, so markers are
     * uniformly visible; label_visibility comes from $labels.
     *
     * @param string $events_state_string the ?events= value
     * @param bool   $labels              the ?eventLabels= value
     * @return array{0: string[], 1: array<string, array{marker_visibility: bool, label_visibility: bool}>}
     */
    public static function selectionsFromUrlParams(string $events_state_string, bool $labels): array
    {
        return [self::parse($events_state_string), self::visibilityFromLabels($labels)];
    }

    /**
     * Build a per-source visibility map from the global eventLabels flag.
     * Markers are always visible (the legacy URL API has no per-source marker
     * toggle); label_visibility comes from $labels. One entry per known source.
     *
     * @return array<string, array{marker_visibility: bool, label_visibility: bool}>
     */
    public static function visibilityFromLabels(bool $labels): array
    {
        $visibility = [];
        foreach (array_keys(EventTypeCatalogue::MAP) as $source) {
            $visibility[$source] = [
                'marker_visibility' => true,
                'label_visibility'  => $labels,
            ];
        }

        return $visibility;
    }
}
