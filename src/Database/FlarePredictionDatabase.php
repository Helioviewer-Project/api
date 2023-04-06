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
    public static function getLatestFlarePredictions(DateTime $observationTime): array
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
                        LEFT JOIN flare_datasets dataset ON p.dataset_id = dataset.id",
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date),
                            $db->link->real_escape_string($date));
        try {
            $result = $db->query($sql);
            return $result->fetch_all(MYSQLI_ASSOC);
        } catch (Exception $e) {
            error_log("Error querying flare predictions: " . $e->getMessage());
            return array();
        }
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
            'name' => 'Solar Flare Predictions',
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
        $predictions = self::getLatestFlarePredictions(new DateTime($date));
        $hef_predictions = self::NormalizePredictions($date, $predictions);
        return [$hef_predictions];
    }
}