<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Statistics Class definition
 *
 * @category Database
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project/
 */

class Database_Statistics {

    private $_dbConnection;

    /**
     * Constructor
     *
     * @return void
     */
    public function __construct() {
        include_once HV_ROOT_DIR.'/../src/Database/DbConnection.php';
        $this->_dbConnection = new Database_DbConnection();
    }

    /**
     * Add a new entry to the `statistics` table
     *
     * param $action string The API action to log
     *
     * @return boolean
     */
    public function log($action) {
        $sql = sprintf(
                  "INSERT INTO statistics "
                . "SET "
                .     "id "        . " = NULL, "
                .     "timestamp " . " = NULL, "
                .     "action "    . " = '%s';",
                $this->_dbConnection->link->real_escape_string($action)
               );
        try {
            $result = $this->_dbConnection->query($sql);
        }
        catch (Exception $e) {
            return false;
        }

        return true;
    }

    /**
     * Get latest usage statistics as JSON
     *
     * @param  string  Time resolution
     *
     * @return str  JSON
     */
    public function getUsageStatistics($resolution) {
        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

        // Determine time intervals to query
        $interval = $this->_getQueryIntervals($resolution);

        // Array to keep track of counts for each action
        $counts = array(
            "buildMovie"           		=> array(),
            "getClosestData"       		=> array(),
            "getClosestImage"      		=> array(),
            "getJPX"               		=> array(),
            "getJPXClosestToMidPoint" 	=> array(),
            "takeScreenshot"       		=> array(),
            "uploadMovieToYouTube" 		=> array(),
            "embed"                		=> array()
        );

        // Summary array
        $summary = array(
            "buildMovie"           		=> 0,
            "getClosestData"       		=> 0,
            "getClosestImage"      		=> 0,
            "getJPX"               		=> 0,
            "getJPXClosestToMidPoint"   => 0,
            "takeScreenshot"       		=> 0,
            "uploadMovieToYouTube" 		=> 0,
            "embed"                		=> 0
        );

        // Format to use for displaying dates
        $dateFormat = $this->_getDateFormat($resolution);

        // Start date
        $date = $interval['startDate'];

        // Query each time interval
        for ($i = 0; $i < $interval["numSteps"]; $i++) {

            // Format date for array index
            $dateIndex = $date->format($dateFormat);

            // MySQL-formatted date string
            $dateStart = toMySQLDateString($date);

            // Move to end date for the current interval
            $date->add($interval['timestep']);

            // Fill with zeros to begin with
            foreach ($counts as $action => $arr) {
                array_push($counts[$action], array($dateIndex => 0));
            }
            $dateEnd = toMySQLDateString($date);

            $sql = sprintf(
                      "SELECT action, COUNT(id) AS count "
                    . "FROM statistics "
                    . "WHERE "
                    .     "timestamp BETWEEN '%s' AND '%s' "
                    . "GROUP BY action;",
                    $this->_dbConnection->link->real_escape_string($dateStart),
                    $this->_dbConnection->link->real_escape_string($dateEnd)
                   );
            try {
                $result = $this->_dbConnection->query($sql);
            }
            catch (Exception $e) {
                return false;
            }

            // Append counts for each API action during that interval
            // to the appropriate array
            while ($count = $result->fetch_array(MYSQLI_ASSOC)) {
                $num = (int)$count['count'];

                $counts[$count['action']][$i][$dateIndex] = $num;
                $summary[$count['action']] += $num;
            }
        }

        // Include summary info
        $counts['summary'] = $summary;

        return json_encode($counts);
    }

    /**
     * Return date format string for the specified time resolution
     *
     * @param  string  $resolution  Time resolution string
     *
     * @return string  Date format string
     */
    public function getDataCoverageTimeline($resolution, $endDate, $interval,
        $stepSize, $steps) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

        $sql = 'SELECT id, name, description FROM datasources ORDER BY description';
        $result = $this->_dbConnection->query($sql);

        $output = array();

        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            $sourceId = $row['id'];

            $output['sourceId'.$sourceId] = new stdClass;
            $output['sourceId'.$sourceId]->sourceId = $sourceId;
            $output['sourceId'.$sourceId]->label = $row['description'];
            $output['sourceId'.$sourceId]->data = array();
        }

        // Format to use for displaying dates
        switch($resolution) {
        case "5m":
        case "15m":
        case "30m":
            $dateFormat = "Y-m-d H:i";
            break;
        case "1h":
            $dateFormat = "Y-m-d H:i";
            break;
        case "1D":
            $dateFormat = "Y-m-d";
            break;
        case "14D":
        case "1W":
            $dateFormat = "Y-m-d";
            break;
        case "30D":
        case "1M":
        case "3M":
        case "6M":
            $dateFormat = "M Y";
            break;
        case "1Y":
            $dateFormat = "Y";
            break;
        default:
            $dateFormat = "Y-m-d H:i e";
        }


        // Start date
        $date = $endDate->sub($interval);

        // Query each time interval
        for ($i = 0; $i < $steps; $i++) {
            $dateIndex = $date->format($dateFormat); // Format date for array index
            $dateStart = toMySQLDateString($date);   // MySQL-formatted date string

            // Move to end date for the current interval
            $date->add($stepSize);

            // Fill with zeros to begin with
            foreach ($output as $sourceId => $arr) {
                array_push($output[$sourceId]->data, array($dateIndex => 0));
            }
            $dateEnd = toMySQLDateString($date);

            $sql = "SELECT sourceId, SUM(count) as count FROM data_coverage_30_min " .
                   "WHERE date BETWEEN '$dateStart' AND '$dateEnd' GROUP BY sourceId;";
            //echo "\n<br />";

            $result = $this->_dbConnection->query($sql);

            // And append counts for each sourceId during that interval to the relevant array
            while ($count = $result->fetch_array(MYSQLI_ASSOC)) {
                $num = (int) $count['count'];
                $output['sourceId'.$count['sourceId']]->data[$i][$dateIndex] = $num;
            }
        }

        return json_encode($output);
    }

    /**
     * Gets latest datasource coverage and return as JSON
     */
    public function getDataCoverage($layers, $resolution, $startDate, $endDate, $eventsStr) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
		
		$distance = $endDate->getTimestamp() - $startDate->getTimestamp();
		$interval = new DateInterval('PT'.$distance.'S');
		
		$startDate->modify('-'.$distance.' seconds');
		$endDate->modify('+'.$distance.' seconds');
		
		$dateStart = toMySQLDateString($startDate);
		$dateEnd = toMySQLDateString($endDate);
		
		$startTimestamp = $startDate->getTimestamp();
		$endTimestamp = $endDate->getTimestamp();
		
		$dateStartISO = str_replace("Z", "", toISOString($startDate));
		$dateEndISO = str_replace("Z", "", toISOString($endDate));
		
        $sources = array();
		$events = array();
		if(!empty($eventsStr)){
			include_once HV_ROOT_DIR.'/../src/Event/HEKAdapter.php';
			$hek = new Event_HEKAdapter();
        	$events = $hek->getFRMsByType($dateStartISO, $dateEndISO, $eventsStr);
        	return json_encode($events);
		}else{
			
			if(!$layers){
				return json_encode(array());
			}
			
			$layersArray = array();
			$layersKeys = array();
			$layersCount = 0;
			foreach($layers->toArray() as $layer){
				$sourceId = $layer['sourceId'];
				
	            $sources[$layersCount] = new stdClass;
	            $sources[$layersCount]->sourceId = $sourceId;
	            $sources[$layersCount]->name = (isset($layer['uiLabels'][0]['name']) ? $layer['uiLabels'][0]['name'] : '').' '.(isset($layer['uiLabels'][1]['name']) ? $layer['uiLabels'][1]['name'] : '').' '.(isset($layer['uiLabels'][2]['name']) ? $layer['uiLabels'][2]['name'] : '');
	            $sources[$layersCount]->data = array();
	            
		        //$sources[$layersCount]->data[] = array($startDate->getTimestamp()*1000, null);
	            
	            $layersArray[] = $sourceId;
	            $layersKeys[$sourceId] = $layersCount;
	            $layersCount++;
	        }
			
			$layersString = implode(' OR sourceId = ', $layersArray);
			
			switch ($resolution) {
		        case 'm':
		            $sql = 'SELECT date AS time,
					       COUNT(*) AS count,
					       sourceId
					FROM data
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, time
					ORDER BY time;';
					
		            break;
		        case '5m':
		            $sql = 'SELECT 
		            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 300)) * 300) as time,
							COUNT(*) AS count,
							sourceId
					FROM data
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, time
					ORDER BY time;';
					
					$beginInterval = new DateTime();
					$endInterval = new DateTime();
					$beginInterval->setTimestamp(floor($startTimestamp / 300) * 300);
					$endInterval->setTimestamp(floor($endTimestamp / 300) * 300);
					
					$interval = DateInterval::createFromDateString('5 minutes');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        case '15m':
		            $sql = 'SELECT 
		            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 900)) * 900) as time,
							COUNT(*) AS count,
							sourceId
					FROM data
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, time
					ORDER BY time;';
					
					$beginInterval = new DateTime();
					$endInterval = new DateTime();
					$beginInterval->setTimestamp(floor($startTimestamp / 900) * 900);
					$endInterval->setTimestamp(floor($endTimestamp / 900) * 900);
					
					$interval = DateInterval::createFromDateString('15 minutes');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        case '30m':
		            $sql = 'SELECT 
		            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 1800)) * 1800) as time,
							COUNT(*) AS count,
							sourceId
					FROM data
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, time
					ORDER BY time;';
					
					$beginInterval = new DateTime();
					$endInterval = new DateTime();
					$beginInterval->setTimestamp(floor($startTimestamp / 1800) * 1800);
					$endInterval->setTimestamp(floor($endTimestamp / 1800) * 1800);
					
					$interval = DateInterval::createFromDateString('30 minutes');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;         
		        case 'h':
		            $sql = 'SELECT DATE_FORMAT(date, "%Y-%m-%d %H:00:00") AS time,
					       COUNT(*) AS count,
					       sourceId
					FROM data
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, time
					ORDER BY time;';
					
					$beginInterval = new DateTime(date('Y-m-d H:00:00', $startTimestamp));
					$endInterval = new DateTime(date('Y-m-d H:00:00', $endTimestamp));
					
					$interval = DateInterval::createFromDateString('1 hour');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        case 'D':
		        	$sql = 'SELECT DATE(date) AS time,
					       SUM(count) AS count,
					       sourceId
					FROM data_coverage_30_min
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, DATE(time)
					ORDER BY DATE(time);';
					
					$beginInterval = new DateTime(date('Y-m-d 00:00:00', $startTimestamp));
					$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
					
					$interval = DateInterval::createFromDateString('1 day');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        case 'M':
		        	$sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-%m-01")) AS time,
					       SUM(count) AS count,
					       sourceId
					FROM data_coverage_30_min
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, DATE(DATE_FORMAT(date, "%Y-%m-01"))
					ORDER BY DATE(DATE_FORMAT(date, "%Y-%m-01"));';
					
					$beginInterval = new DateTime(date('Y-m-01 00:00:00', $startTimestamp));
					$endInterval = new DateTime(date('Y-m-01 00:00:00', $endTimestamp));
					
					$interval = DateInterval::createFromDateString('1 month');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        case 'Y':
		            $sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-01-01")) AS time,
					       SUM(count) AS count,
					       sourceId
					FROM data_coverage_30_min
					WHERE (sourceId = '.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
					GROUP BY sourceId, DATE(DATE_FORMAT(date, "%Y-01-01"))
					ORDER BY DATE(DATE_FORMAT(date, "%Y-01-01"));';
					
					$beginInterval = new DateTime(date('Y-01-01 00:00:00', $startTimestamp));
					$endInterval = new DateTime(date('Y-01-01 00:00:00', $endTimestamp));
					
					$interval = DateInterval::createFromDateString('1 year');
					$period = new DatePeriod($beginInterval, $interval, $endInterval);
					
		            break;
		        default:
		            $msg = 'Invalid resolution specified. Valid options include: ' . implode(', ', $validRes);
		            throw new Exception($msg, 25);
		    }
			
			//build 0 data array
			if($resolution != 'm'){
				$emptyData = array();
				foreach ( $period as $dt ){
					$emptyData[ $dt->getTimestamp() ] = 0;
				}
			}
			
			//Procceed SQL Data
			$result = $this->_dbConnection->query($sql);
			$dbData = array();
			while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
	            $num = (int) $row['count'];
	            $sourceId = $row['sourceId'];
	            $key = $layersKeys[$sourceId];
	            if($resolution == 'm'){
		            $sources[$key]->data[] = array(strtotime($row['time'])*1000, $key+1);
	            }else{
		            $dbData[$key][strtotime($row['time'])] = $num;
	            }
	        }
	        
	        //Fill 0 values rows
	        if($resolution != 'm'){
		        foreach($layersKeys as $sourceId=>$key){
			        foreach($emptyData as $timestamp=>$count){
				        if(isset($dbData[$key]) && isset($dbData[$key][ $timestamp ])){
					        $count = $dbData[$key][ $timestamp ];
				        }
				        $sources[$key]->data[] = array($timestamp*1000, $count);
			        }
		        }
	        }
	        //foreach($sources as $sourceId=>$row){
		    //    $sources[$sourceId]->data[] = array($endDate->getTimestamp()*1000, null);
	        //}
	        
	        return json_encode($sources);
		}
    }

    /**
     * Update data source coverage data for the last 7 Days
     * (or specified time period).
     */
    public function updateDataCoverage($period=null) {

        if ( gettype($period) == 'string' &&
             preg_match('/^([0-9]+)([mhDMY])$/', $period, $matches) === 1 ) {

            $magnitude   = $matches[1];
            $period_abbr = $matches[2];
        }
        else {
            $magnitude   =  7;
            $period_abbr = 'D';
        }

        switch ($period_abbr) {
        case 'm':
            $interval = 'INTERVAL '.$magnitude.' MINUTE';
            break;
        case 'h':
            $interval = 'INTERVAL '.$magnitude.' HOUR';
            break;
        case 'D':
            $interval = 'INTERVAL '.$magnitude.' DAY';
            break;
        case 'M':
            $interval = 'INTERVAL '.$magnitude.' MONTH';
            break;
        case 'Y':
            $interval = 'INTERVAL '.$magnitude.' YEAR';
            break;
        default:
            $interval = 'INTERVAL 7 DAY';
        }

        $sql = 'REPLACE INTO ' .
                    'data_coverage_30_min ' .
                '(date, sourceId, count) ' .
                'SELECT ' .
                    'SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE ' .
                    'CONCAT( ' .
                        'DATE_FORMAT(date, "%Y-%m-%d %H:"), '    .
                        'LPAD((MINUTE(date) DIV 30)*30, 2, "0"), ' .
                        '":00") AS "bin", ' .
                    'sourceId, ' .
                    'COUNT(id) ' .
                'FROM ' .
                    'data ' .
                'WHERE ' .
                    'date >= DATE_SUB(NOW(),'.$interval.') ' .
                'GROUP BY ' .
                    'bin, ' .
                    'sourceId;';
        $result = $this->_dbConnection->query($sql);


        $output = array(
            'result'     => $result,
            'interval'     => $interval
        );

        return json_encode($output);
    }

    /**
     * Determines date format to use for the x-axis of the requested resolution
     */
    private function _getDateFormat($resolution) {
        switch ($resolution) {
            case "hourly":
                return "ga";  // 4pm
                break;
            case "daily":
                return "D";   // Tues
                break;
            case "weekly":
                return "M j"; // Feb 3
                break;
            case "monthly":
                return "M y"; // Feb 09
                break;
            case "yearly":
                return "Y";   // 2009
                break;
        }
    }

    /**
     * Determine time inveral specification for statistics query
     *
     * @param  string  $resolution  Time resolution string
     *
     * @return array   Array specifying a time interval
     */
    private function _getQueryIntervals($resolution) {

        date_default_timezone_set('UTC');

        // Variables
        $date     = new DateTime();
        $timestep = null;
        $numSteps = null;

        // For hourly resolution, keep the hours value, otherwise set to zero
        $hour = ($resolution == "hourly") ? (int) $date->format("H") : 0;

        // Round end time to nearest hour or day to begin with (may round other units later)
        $date->setTime($hour, 0, 0);

        // Hourly
        if ($resolution == "hourly") {
            $timestep = new DateInterval("PT1H");
            $numSteps = 24;

            $date->add($timestep);

            // Subtract 24 hours
            $date->sub(new DateInterval("P1D"));
        }

        // Daily
        else if ($resolution == "daily") {
            $timestep = new DateInterval("P1D");
            $numSteps = 28;

            $date->add($timestep);

            // Subtract 4 weeks
            $date->sub(new DateInterval("P4W"));
        }

        // Weekly
        else if ($resolution == "weekly") {
            $timestep = new DateInterval("P1W");
            $numSteps = 26;

            $date->add(new DateInterval("P1D"));

            // Subtract 25 weeks
            $date->sub(new DateInterval("P25W"));
        }

        // Monthly
        else if ($resolution == "monthly") {
            $timestep = new DateInterval("P1M");
            $numSteps = 24;

            $date->modify('first day of next month');
            $date->sub(new DateInterval("P24M"));
        }

        // Yearly
        else if ($resolution == "yearly") {
            $timestep = new DateInterval("P1Y");
            $numSteps = 8;

            $year = (int) $date->format("Y");
            $date->setDate($year - $numSteps + 1, 1, 1);
        }

        // Array to store time intervals
        $intervals = array(
            "startDate" => $date,
            "timestep"  => $timestep,
            "numSteps"  => $numSteps
        );

        return $intervals;
    }
}
?>
