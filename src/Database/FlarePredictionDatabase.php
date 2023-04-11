<?php declare(strict_types=1);

/**
 * Interface for processing flare predictions from the database
 */
class Database_FlarePredictionDatabase
{
    static private $db = null;

    static private function get_db()
    {
        if (self::$db === null) {
            include_once __DIR__ . "/DbConnection.php";
            self::$db = new Database_DbConnection();
        }
        return self::$db;
    }

    /**
     * Returns the latest flare predictions for the given observation time.
     *
     * @param DateTime $observationTime
     *
     * @return array
     */
    public static function getLatestFlarePredictions(DateTime $observationTime, ?array $datasets): array
    {
        $db = self::get_db();
        $date = $observationTime->format('Y-m-d H:i:s');
        // Not sure how well this query will work when the database gets large, but we'll find out in time.
        // Breakdown of query:
        // The inner join query gets the minimum time difference between the issue time and the observation time for each
        // dataset where the issue time is before the dataset and the observation time is within the prediction window.
        // The join condition filters for the predictions that meet the minimum time differences.
        $sql = sprintf("SELECT p.id,
                               p.start_window,
                               p.end_window,
                               p.issue_time,
                               p.c,
                               p.m,
                               p.x,
                               p.cplus,
                               p.mplus,
                               p.latitude,
                               p.longitude,
                               p.hpc_x,
                               p.hpc_y,
                               dataset.name as dataset
                        FROM flare_predictions p
                        INNER JOIN
                            (SELECT dataset_id, MIN(TIMESTAMPDIFF(second, issue_time, '%s')) as dt
                            FROM flare_predictions
                            WHERE issue_time < '%s'
                                AND '%s' BETWEEN start_window AND end_window
                            GROUP BY dataset_id) g
                        ON p.dataset_id = g.dataset_id
                        AND TIMESTAMPDIFF(second, p.issue_time, '%s') = g.dt
                        LEFT JOIN flare_datasets dataset ON p.dataset_id = dataset.id
                        %s",
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date),
                            self::GetDatasetWhereClause($db, "WHERE", $datasets));
        try {
            $result = $db->query($sql);
            $predictions = $result->fetch_all(MYSQLI_ASSOC);
            return self::PatchPredictions($predictions);
        } catch (Exception $e) {
            error_log("Error querying flare predictions: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Performs postprocessing on prediction data and returns it.
     * - ASSA_* predictions seem to have their lat/long flipped...
     */
    private static function PatchPredictions(array $predictions): array {
        foreach ($predictions as &$prediction) {
            if (strpos($prediction['dataset'], "ASSA") !== false) {
                $prediction['original_latitude'] = $prediction['latitude'];
                $prediction['original_longitude'] = $prediction['longitude'];
                $tmp = $prediction['latitude'];
                $prediction['latitude'] = $prediction['longitude'];
                $prediction['longitude'] = $tmp;
            }
        }
        return $predictions;
    }

    /**
     * Returns a condition that "dataset.name" is in the array of given datasets.
     * Insert into a SQL query to filter by dataset
     * @param Database_DbConnection $db Database connection object
     * @param string $prefix SQL prefix (WHERE or AND or OR). This will be placed before the condition
     * @param array $datasets Datasets to filter by. If empty, then no condition is returned.
     * @return string SQL query or empty string. This can be inserted directly into the query, it already performs the character escape.
     */
    private static function GetDatasetWhereClause($db, string $prefix, ?array $datasets): string {
        if (isset($datasets)) {
            $extra_where = "";
            if (count($datasets) > 0 && ($datasets[0] != 'all')) {
                $extra_where = "dataset.name in (";
                foreach ($datasets as $dataset) {
                    $extra_where .= sprintf("'%s',", $db->link->real_escape_string($dataset));
                }
                // trim trailing comma and add closing parenthesis
                $extra_where = substr($extra_where, 0, strlen($extra_where)-1) . ")";
                return "$prefix $extra_where";
            }
        }
        return "";
    }

    /**
     * Returns all prediction information for predictions in the given time range
     */
    public static function getFlarePredictionsInRange(DateTime $start, DateTime $end, array $datasets): array {
        $db = self::get_db();
        $startDateStr = $start->format('Y-m-d H:i:s');
        $endDateStr = $end->format('Y-m-d H:i:s');

        $sql = sprintf("SELECT p.*,
                               dataset.name as dataset
                        FROM flare_predictions p
                        LEFT JOIN flare_datasets dataset ON dataset.id = p.dataset_id
                        WHERE p.issue_time BETWEEN '%s' AND '%s'
                        %s
                        ",
                            $db->link->real_escape_string($startDateStr),
                            $db->link->real_escape_string($endDateStr),
                            self::GetDatasetWhereClause($db, "AND", $datasets));
        try {
            $result = $db->query($sql);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error querying flare prediction time range: " . $e->getMessage());
            return array();
        }
    }

    /**
     * Counts the number of flares between start and end in buckets of binSizeSeconds.
     * @return array Prediction counts
     * @example | 1680332400 |    14 |
     *          | 1680336000 |     8 |
     *          | 1680339600 |    10 |
     *          | 1680343200 |    11 |
     *          | 1680346800 |    11 |
     *          | 1680350400 |    14 |
     *          | 1680352200 |     8 |
     *          | 1680354000 |    14 |
     *          | 1680357600 |    11 |
     *          | 1680361200 |     9 |
     *          | 1680364800 |    11 |
     *          | 1680368400 |     4 |
     *          | 1680372000 |     6 |
     */
    public static function getFlarePredictionCounts(DateTime $start, DateTime $end, int $binSizeSeconds, ?array $datasets): array {
        $db = self::get_db();
        $startDateStr = $start->format('Y-m-d H:i:s');
        $endDateStr = $end->format('Y-m-d H:i:s');
        $sql = sprintf("SELECT UNIX_TIMESTAMP(start_window) as timestamp, COUNT(*) as count
                        FROM flare_predictions
                        WHERE end_window >= '%s' AND start_window <= '%s'
                        %s
                        GROUP BY timestamp DIV %d
                        ORDER BY timestamp",
                            $db->link->real_escape_string($startDateStr),
                            $db->link->real_escape_string($endDateStr),
                            self::GetDatasetWhereClause($db, "AND", $datasets),
                            $db->link->real_escape_string("$binSizeSeconds")
                        );
        try {
            $result = $db->query($sql);
            $data = $result->fetch_all(MYSQLI_ASSOC);
            return self::BinsToArray($data, $binSizeSeconds);
        } catch (Exception $e) {
            error_log("Error querying flare prediction counts: " . $e->getMessage());
            error_log($sql);
            return array();
        }
    }

    /**
     * Transforms the SQL result from getFlarePredictionCounts from SQL columns into a 2D array
     * expected by the getDataCoverage API.
     * TODO: This could be a moved to a helper API where $data is always a list of arrays with timestamp/count keys.
     * @return array list of lists where each internal list is [milliseconds, count] representing the timestamp/count buckets.
     */
    private static function BinsToArray(array $data, int $binSizeSeconds) {
        $pairs = [];
        // Get the starting timestamp for the data
        if (count($data) > 0) {
            $expected_timestamp = intval($data[0]['timestamp']);
        }
        // Iterate through the data turning the timestamp, counts into pairs
        foreach ($data as $time_count_pair) {
            $time = intval($time_count_pair['timestamp']);
            $count = intval($time_count_pair['count']);
            // If time doesn't match the expected timestamp, it's because the query returned no data for that bin.
            // In that case, add 0 to the pair array
            while ($expected_timestamp < $time) {
                array_push($pairs, [$expected_timestamp * 1000, 0]);
                $expected_timestamp += $binSizeSeconds;
            }
            array_push($pairs, [$time * 1000, $count]);
            $expected_timestamp += $binSizeSeconds;
        }
        return $pairs;
    }

    /**
     * Normalizes a set of predictions into the Helioviewer Event Format.
     */
    public static function NormalizePredictions(string $date, array $predictions): array {
        include_once HV_ROOT_DIR.'/../scripts/rot_hpc.php';
        // Normalize the flare predictions into Helioviewer Event Format
        $datasets = [];
        foreach ($predictions as &$prediction) {
            list($prediction['hv_hpc_x'], $prediction['hv_hpc_y']) = rot_hpc($prediction['hpc_x'], $prediction['hpc_y'], $prediction['start_window'], $date);
            $prediction['label'] = self::CreateLabel($prediction);
            $prediction['version'] = "";
            $prediction['type'] = 'FP';
            $prediction['start'] = $prediction['start_window'];
            $prediction['end'] = $prediction['end_window'];
            if (!array_key_exists($prediction['dataset'], $datasets)) {
                $datasets[$prediction['dataset']] = [
                    'name' => $prediction['dataset'],
                    'contact' => '',
                    'url' => 'https://ccmc.gsfc.nasa.gov/scoreboards/flare/',
                    'data' => []
                ];
            }
            array_push($datasets[$prediction['dataset']]['data'], $prediction);
        }

        $hef_predictions = [
            'name' => 'Solar Flare Prediction',
            'pin' => 'FP',
            'groups' => []
        ];
        foreach ($datasets as $_ => $details) {
            array_push($hef_predictions['groups'], $details);
        }
        return $hef_predictions;
    }

    /**
     * Handle the case where all flare prediction values are null
     */
    private static function LabelNoPrediction(array $prediction, string $label): string {
        $flare_classes = ["c", "cplus", "m", "mplus", "x"];
        $all_null = true;
        foreach ($flare_classes as $flare_class) {
            if ($prediction[$flare_class] != null) {
                $all_null = false;
            }
        }

        if ($all_null) {
            return $label . "\nNo probabilities given";
        }
        return $label;
    }

    /**
     * Returns a flare prediction value as a string representation.
     * Example: FlarePredictionString($prediction, "c") -> "c: 0.75"
     */
    private static function FlarePredictionString(array $prediction, string $flare_class): string {
        if (array_key_exists($flare_class, $prediction) && $prediction[$flare_class] != null) {
            // Probability
            $probability = floatval($prediction[$flare_class]) * 100;
            // Make sure the flare class is uppercase in the label
            $tentative_label = strtoupper($flare_class) . ": " . round($probability, 2) . "%";
            // Replace the word "Plus" in the label with the plus sign.
            $tentative_label = str_replace("PLUS", "+", $tentative_label);
            return $tentative_label;
        }
        return "";
    }

    /**
     * Adds the flare prediction as a newline on the label.
     * @return string the new label with the flare string appended to it or the original label if the flare class isn't in the prediction.
     */
    private static function AppendFlarePredictionToLabel(array $prediction, string $flare_class, string $label) {
        $flare_label = self::FlarePredictionString($prediction, $flare_class);
        if ($flare_label != "") {
            $label .= "\n$flare_label";
        }
        return $label;
    }

    /**
     * Creates the label text that shows up on Helioviewer for the flare prediction
     */
    private static function CreateLabel(array $prediction): string {
        // Remove underscores and replace them with spaces in the dataset name
        $label = str_replace("_", " ", $prediction['dataset']);
        // Make the label use Pascal Case (Every word's first letter is capitalized)
        $label = ucwords(strtolower($label));

        // Add the flare prediction values to the label
        $label = self::AppendFlarePredictionToLabel($prediction, "c", $label);
        $label = self::AppendFlarePredictionToLabel($prediction, "cplus", $label);
        $label = self::AppendFlarePredictionToLabel($prediction, "m", $label);
        $label = self::AppendFlarePredictionToLabel($prediction, "mplus", $label);
        $label = self::AppendFlarePredictionToLabel($prediction, "x", $label);
        $label = self::LabelNoPrediction($prediction, $label);

        return $label;
    }

    public static function GetLatestNormalizedFlarePredictions(string $date): array {
        $predictions = self::getLatestFlarePredictions(new DateTime($date), null);
        $hef_predictions = self::NormalizePredictions($date, $predictions);
        return [$hef_predictions];
    }

    /**
     * Returns prediction coverage statistics for the given time range
     * @return array
     */
    public static function GetPredictionCoverage(array $eventDetails, string $resolution, DateTime $startDate, DateTime $endDate, DateTime $currentDate): array {
        if (in_array($resolution, ["m", "5m", "15m"])) {
            $datasets = explode(";", $eventDetails["frm_name"]);
            $predictions = self::getFlarePredictionsInRange($startDate, $endDate, $datasets);
            return self::PredictionsToCoverageFormat($predictions);
        } else {
            // use bucket format
            $seconds = self::ResolutionToSeconds($resolution);
            $datasets = explode(";", $eventDetails["frm_name"]);
            return self::getFlarePredictionCounts($startDate, $endDate, $seconds, $datasets);
        }
    }

    private const MAP = [
        "30m" => 1800,
        "h"   => 3600,
        "D"   => 86400,
        "W"   => 604800,
        "M"   => 18144000, // 30 days
        "Y"   => 31536000  // 365 days
    ];
    private static function ResolutionToSeconds(string $resolution): int {
        if (array_key_exists($resolution, self::MAP)) {
            return self::MAP[$resolution];
        } else {
            error_log("Invalid resolution requested for prediction coverage query");
            return 1800;
        }
    }

    /**
     * Converts an array of prediction data into the coverage format required for the image timeline
     * @return array
     */
    private static function PredictionsToCoverageFormat(array $predictions): array {
        $coverage = [];
        foreach ($predictions as $idx => $prediction) {
            array_push($coverage, self::PredictionToCoverageObject($prediction, $idx));
        }
        return $coverage;
    }

    /**
     * Converts a prediction object into a coverage object
     * Each object in the array should look like this:
     * [
     *                      x: unix timestamp in milliseconds of the event's start time,
     *                     x2: unix timestamp in milliseconds of the event's end time,
     *                      y: index of this item in the array,
     *            kb_archivid: unique id for this event,
     *    hv_labels_formatted: array of key value pairs which make up a human readable label,
     *             event_type: Event type abbreviation,
     *               frm_name: Name for the event,
     *         frm_specificid: Version of the recognition method, or empty string,
     *         event_peaktime: Peak time or null (as string in format Y-m-d H:i:s)
     *        event_starttime: Start time of the event,
     *          event_endtime: End time of the event.
     *                concept: The overall type of event that this is,
     *               modifier: 0
     * ]
     */
    private static function PredictionToCoverageObject(array $prediction, int $y): array {
        $start = new DateTime($prediction['start_window']);
        $end = new DateTime($prediction['end_window']);
        return [
                      'x' => $start->getTimestamp() * 1000,
                     'x2' => $end->getTimestamp() * 1000,
                      'y' => $y,
            'kb_archivid' => $prediction['sha256'],
            'hv_labels_formatted' => self::CreateCoverageLabel($prediction),
            "event_type"    => "FP",
            "frm_name"      => $prediction['dataset'],
            "frm_specificid" => "",
            "event_peaktime" => $prediction["issue_time"],
            "event_starttime" => $prediction["start_window"],
            "event_endtime"  => $prediction["end_window"],
            "concept" => "Solar Flare Prediction",
            "modifier" => 0
        ];
    }

    private static function CreateCoverageLabel(array $prediction): array {
        $label = [];
        $classes = ['c', 'cplus', 'm', 'mplus', 'x'];
        foreach ($classes as $class) {
            if (isset($prediction[$class])) {
                $key = str_replace("PLUS", "+", strtoupper($class));
                $label[$key] = round($prediction[$class] * 100, 2) . "%" ;
            }
        }
        return $label;
    }
}
