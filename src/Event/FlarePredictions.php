<?php declare(strict_types=1);

use FlareScoreboard\HapiIterator;
use FlareScoreboard\HapiRecord;
use FlareScoreboard\Scoreboard;

/**
 * FlarePredictions
 * Interface for loading data from CCMC flare scoreboard
 *
 * @category Event
 * @package  Helioviewer
 * @author   Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class FlarePredictions
{
    const PREDICTION_METHODS = [
        "SIDC_Operator_REGIONS",
        "BoM_flare1_REGIONS",
        "ASSA_1_REGIONS",
        "ASSA_24H_1_REGIONS",
        "AMOS_v1_REGIONS",
        "ASAP_1_REGIONS",
        "MAG4_LOS_FEr_REGIONS",
        "MAG4_LOS_r_REGIONS",
        "MAG4_LOS_FEr_REGIONS",
        "MAG4_LOS_r_REGIONS",
        "MAG4_SHARP_FE_REGIONS",
        "MAG4_SHARP_REGIONS",
        "MAG4_SHARP_HMI_REGIONS",
        "AEffort_REGIONS"
    ];

    /**
     * Returns data from the flare scoreboard in the style of a HEK Event
     * @param string $startTime Start time of the event in the format "YYYY-MM-DDTHH:MM:SS"
     * @param string $stopTime Stop time of the event in the format "YYYY-MM-DDTHH:MM:SS"
     * @return array Array of Flare predictions
     */
    static public function getEvents(string $startTime, string $stopTime): array
    {
        include_once HV_ROOT_DIR . '/../src/FlareScoreboard/vendor/autoload.php';
        $scoreboard = new Scoreboard();
        $results = array();
        foreach (self::PREDICTION_METHODS as $method) {
            $predictions = $scoreboard->getPredictions($method, new DateTime($startTime), new DateTime($stopTime));
            $predictionEvents = self::predictionsToEvents($predictions, $method);
            $results = array_merge($results, $predictionEvents);
        }
        return $results;
    }

    /**
     * Convert heliographic stonyhurst (lat/long) to helioprojective (x,y)
     */
    static private function hgs2hpc(float $lat, float $lon, string $time): array
    {
        // If the python server is not running, start it
        if (!file_exists("/tmp/hgs2hpc.sock")) {
            $cmd = "HOME=/tmp " . HV_PYTHON_PATH . " " . __DIR__ . "/hgs2hpc.py > /tmp/hgs2hpc.log 2>&1 &";
            exec($cmd);
            // Wait up to 5 seconds for the socket to be created
            $elapsed_time = 0;
            $five_seconds = 5000000;
            while (!file_exists("/tmp/hgs2hpc.sock") && $elapsed_time < $five_seconds) {
                $one_hundred_ms = 100000;
                usleep($one_hundred_ms);
                $elapsed_time += $one_hundred_ms;
            }
        }

        // Create a socket to hgs2hpc and send the parameters
        $socket = socket_create(AF_UNIX, SOCK_STREAM, 0);
        socket_set_block($socket);
        if (socket_connect($socket, "/tmp/hgs2hpc.sock") === false) {
            return array("x" => 0.987654321, "y" => 0.123456789);
        }
        $msg = "$lat $lon $time";
        socket_write($socket, $msg, strlen($msg));
        // Read back the result
        $result = socket_read($socket, 1024);
        // Send the message that we're done
        $msg = "quit";
        socket_write($socket, $msg, strlen($msg));
        // Socket should have closed on the other end, now close it here.
        socket_close($socket);

        // Parse the result
        $result = explode(" ", $result);
        return array("x" => floatval($result[0]), "y" => floatval($result[1]));
    }

    /**
     * Returns information about the possible latitude fields for a given prediction.
     * @note Predictions can have their latitude in the fields "NOAALatitude", "CataniaLatitude", or "ModelLatitude"
     *       this makes their processing more complicated, we have to figure out which fields are available, and which one has valid data.
     * @param array $data Prediction data (HAPI Style)
     * @param array $parameters Parameter keys for the data array (HAPI Style)
     */
    static protected function get_latitude(HapiRecord &$data): ?int
    {
        $noaaLatitude = $data["NOAALatitude"];
        $cataniaLatitude = $data["CataniaLatitude"];
        $modelLatitude = $data["ModelLatitude"];
        // Assumption here is that 2/3 of these are going to be null depending on which field is set.
        return $noaaLatitude ?? $cataniaLatitude ?? $modelLatitude;
    }

    /**
     * Returns information about the possible latitude fields for a given prediction.
     * @param array $data Prediction data (HAPI Style)
     * @param array $parameters Parameter keys for the data array (HAPI Style)
     */
    static protected function get_longitude(HapiRecord &$data): ?int
    {
        $noaaLongitude = $data["NOAALongitude"];
        $cataniaLongitude = $data["CataniaLongitude"];
        $modelLongitude = $data["ModelLongitude"];
        // Assumption here is that 2/3 of these are going to be null depending on which field is set.
        return $noaaLongitude ?? $cataniaLongitude ?? $modelLongitude;
    }

    /**
     * Generates a unique identifier for the given record.
     * Using the hash of the record is sufficient and will ensure we don't have any duplicates.
     */
    static protected function generate_unique_id(HapiRecord &$data): string
    {
        $json = json_encode($data);
        return md5($json);
    }

    /**
     * Creates the name of the even that shows up on the pin's label in Helioviewer.
     */
    static protected function generate_frm_name(string $method): string
    {
        $method = str_replace("_", " ", $method);
        $method = str_replace(" REGIONS", "", $method);
        $method = ucwords(strtolower($method));
        return "Prediction $method";
    }

    /**
     * Attempts to create a record from the given prediction data.
     * @param HapiRecord $data prediction data wrapped with the HapiRecord class
     * @return array|null HEK style event. Null if the event is missing its latitude and longitude
     */
    static protected function create_event(HapiRecord &$data, string $method): ?array
    {
        // Get the correct latitude/longitude field for the specific prediction
        $latitude = self::get_latitude($data);
        $longitude = self::get_longitude($data);
        if (is_null($latitude) || is_null($longitude)) {
            return null;
        }
        $hpc = self::hgs2hpc($latitude, $longitude, $data["start_window"]);
        // Fields come from the HEK spec https://www.lmsal.com/hek/VOEvent_Spec.html
        // This is technically not a valid HEK event since it doesn't contain all required fields, but it's enough for Helioviewer.
        $event = array(
            "event_type" => "FL",
            // kb_archivid must be unique.
            "kb_archivid" => self::generate_unique_id($data),
            "kb_archivist" => "CCMC Flare Scoreboard",

            "event_endtime" => $data["end_window"],
            "event_starttime" => $data["start_window"],

            "frm_contact" => "CCMC",
            "frm_daterun" => $data["issue_time"],
            "frm_name" => self::generate_frm_name($method),
            "frm_url" => "https://ccmc.gsfc.nasa.gov/scoreboards/flare/",

            // Used by helioviewer.
            "event_testflag" => "false",
            "hpc_x" => $hpc['x'],
            "hpc_y" => $hpc['y'],
            "hpc_boundcc" => "",
            "concept" => "Flare"
        );
        $event["fl_C"] = $data["C"];
        $event["fl_M"] = $data["M"];
        $event["fl_C+"] = $data["CPlus"];
        $event["fl_M+"] = $data["MPlus"];
        $event["fl_X"] = $data["X"];
        return $event;
    }

    /**
     * Converts a list of predictions into a style compatible with HEK events
     * @param array $predictions Response from the scoreboard's getPredictions method
     * @return array Array of HEK style predictions
     */
    static protected function predictionsToEvents(HapiIterator &$predictions, string $method): array
    {
        $events = array();
        // Iterate over each prediction
        foreach ($predictions as $record) {
            // Create a HEK style event with the given lat/long
            $event = self::create_event($record, $method);
            if (is_null($event)) {
                continue;
            }
            array_push($events, $event);
            break;
        }
        return $events;
    }
}