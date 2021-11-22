<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Statistics Class definition
 *
 * @category Database
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
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
     * Add a new entry to the `movies_jpx` table
     *
     * param $action string The API action to log
     *
     * @return boolean
     */
    public function logJPX($reqStartDate, $reqEndDate, $sourceId) {
        $sql = sprintf(
                  "INSERT INTO movies_jpx "
                . "SET "
                .     "id "        . " = NULL, "
                .     "timestamp " . " = NULL, "
                .     "reqStartDate " . " = '%s', "
                .     "reqEndDate " . " = '%s', "
                .     "sourceId " . " = %d;",
                $this->_dbConnection->link->real_escape_string($reqStartDate),
                $this->_dbConnection->link->real_escape_string($reqEndDate),
                $this->_dbConnection->link->real_escape_string($sourceId)
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
	 * Logs api usage to redis.
	 * Logging is done to the nearest hour.
	 * 
	 * $action - (String) the action to log
	 * 
	 * Optional $redis param for initilized connection to redis
	 * API index.php passes $redis in since a connection is established for rate-limit
	 */
	public function logRedis($action, $redis = null){
		try{
			if(!isset($redis)){
				$redis = new Redis();
				$redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);
			}
			$requestDateTime = date("c");
			// Some formatting to sql style date like "2020-07-01T00:00:00"
			$dateTimeNoMilis = str_replace("T", " ", explode("+",$requestDateTime)[0]); // strip out miliseconds and "T"
			$dateTimeNearestHour = explode(":",$dateTimeNoMilis)[0] . ":00:00"; // strip out minutes and seconds

			// Make compound key
			$key = HV_REDIS_STATS_PREFIX . "/" . $dateTimeNearestHour . "/" . $action;
			$redis->incr($key);
		}catch(Exception $e){
			//continue gracefully if redis statistics logging fails
		}
	}

	public function saveStatisticsFromRedis($redis){
		$oneHourAgoSeconds = time() - (60*60);
		$oneHourAgoDateTime = date("c",$oneHourAgoSeconds);//
        // Some formatting to sql style date like "2020-07-01T00:00:00"
		$dateTimeNoMilis = str_replace("T", " ", explode("+",$oneHourAgoDateTime)[0]); // strip out miliseconds and "T"
		$dateTimeNearestHour = explode(":",$dateTimeNoMilis)[0] . ":00:00"; // strip out minutes and seconds

		$statisticsKeys = $redis->keys(HV_REDIS_STATS_PREFIX . "/*");
		$statisticsData = array();
		foreach( $statisticsKeys as $key ){
			$count = (int)$redis->get($key);
			$keyComponents = explode("/",$key);
			// only import data from the previous hour and older.
			if($keyComponents[1] <= $dateTimeNearestHour){
				$statistic = array( 
					"datetime" => $keyComponents[1],
					"action" => $keyComponents[2],
					"count" => $count,
					"key" => $key
				);
				array_push($statisticsData, $statistic);
			}
		}
		foreach($statisticsData as $data){
			$sql = sprintf(
				  "INSERT INTO redis_stats "
				. "SET "
				. "datetime "	. " = '%s', "
				. "action "		. " = '%s', " 
				. "count "		. " = %d "
				. "ON DUPLICATE KEY UPDATE count = %d;",
				$this->_dbConnection->link->real_escape_string($data["datetime"]),
				$this->_dbConnection->link->real_escape_string($data["action"]),
				$this->_dbConnection->link->real_escape_string($data["count"]),
				$this->_dbConnection->link->real_escape_string($data["count"])
			);
			try {
				$result = $this->_dbConnection->query($sql);
				if( $result == 1 ){
					$redis->delete($data["key"]);
				}
			}
			catch (Exception $e) {

			}
		}
		
	}

	public function saveRateLimitExceededFromRedis($redis){
		$oneHourAgoSeconds = time() - (60*60);
		$oneHourAgoDateTime = date("c",$oneHourAgoSeconds);//
        // Some formatting to sql style date like "2020-07-01T00:00:00"
		$dateTimeNoMilis = str_replace("T", " ", explode("+",$oneHourAgoDateTime)[0]); // strip out miliseconds and "T"
		$dateTimeNearestHour = explode(":",$dateTimeNoMilis)[0] . ":00:00"; // strip out minutes and seconds

		$statisticsKeys = $redis->keys(HV_RATE_EXCEEDED_PREFIX . "/*");
		$statisticsData = array();
		foreach( $statisticsKeys as $key ){
			$count = (int)$redis->get($key);
			$keyComponents = explode("/",$key);
			// only import data from the previous hour and older.
			if($keyComponents[2] <= $dateTimeNearestHour){
				$statistic = array( 
					"identifier" => $keyComponents[1],
					"datetime" => $keyComponents[2],
					"count" => $count,
					"key" => $key
				);
				array_push($statisticsData, $statistic);
			}
		}
		foreach($statisticsData as $data){
			$sql = sprintf(
				  "INSERT INTO rate_limit_exceeded "
				. "SET "
				. "datetime "	. " = '%s', "
				. "identifier "	. " = '%s', " 
				. "count "		. " = %d "
				. "ON DUPLICATE KEY UPDATE count = %d;",
				$this->_dbConnection->link->real_escape_string($data["datetime"]),
				$this->_dbConnection->link->real_escape_string($data["identifier"]),
				$this->_dbConnection->link->real_escape_string($data["count"]),
				$this->_dbConnection->link->real_escape_string($data["count"])
			);
			try {
				$result = $this->_dbConnection->query($sql);
				if( $result == 1 ){
					$redis->delete($data["key"]);
				}
			}
			catch (Exception $e) {

			}
		}
		
	}
	
	/**
     * Returns an array of the known data sources as strings 
	 * compatible with dataSourceString used in screenshot and movie tables
	 * 
	 * Modified Copy of getDataSources function in src/Database/ImgIndex.php
     *
     * @return array An array of strings
     */
    public function _getDataSourceStrings() {

        // Support up to 5 levels of datasource hierarchy
        $letters = array('a','b','c','d','e');

        $sql = 'SELECT '
             .     's.name '           .'AS nickname, '
             .     's.id '             .'AS id, '
             .     's.enabled '        .'AS enabled, '
             .     's.layeringOrder '  .'AS layeringOrder, '
             .     's.units '          .'AS units';

        foreach ($letters as $i=>$letter) {
            $sql .= ', ';
            $sql .= $letter.'.name '        .'AS '.$letter.'_name, ';
            $sql .= $letter.'.description ' .'AS '.$letter.'_description, ';
            $sql .= $letter.'.label '       .'AS '.$letter.'_label';
        }

        $sql .= ' FROM datasources s ';

        foreach ($letters as $i=>$letter) {
            $sql .= 'LEFT JOIN datasource_property '.$letter.' ';
            $sql .= 'ON s.id='.$letter.'.sourceId ';
            $sql .= 'AND '.$letter.'.uiOrder='.++$i.' ';
        }

	    $sql .= ' ORDER BY s.displayOrder ASC, a.name ASC;';

        // Use UTF-8 for responses
        $this->_dbConnection->setEncoding('utf8');

        // Fetch available data-sources
        $result = $this->_dbConnection->query($sql);
        $sources = array();
        while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
            array_push($sources, $row);
        }

		// Convert results into a more easily traversable tree structure
        $dataSourceNameStringArray = array();
        foreach ($sources as $source) {

            // Only include if data is available for the specified source
            // as flagged in the `datasources` table
            if ( !(bool)$source["enabled"] ) {
                continue;
            }

            // Determine depth of tree for this data source
            $depth = 0;
            $uiLabels = array();
            foreach ($letters as $i=>$letter) {
                if ( $source[$letter.'_name'] !== null ) {
                    $depth = ++$i;
                }
			}
			//concatenate the full data source name based on the depth
			$fullDataSourceName = '';
			foreach ($letters as $index=>$letter) {
				$partialName = $source[$letter.'_name'];
				if ( $index == $depth ) {
					break;
				}
				$fullDataSourceName = $fullDataSourceName.$partialName.' ';
				$index++;
			}
			//remove trailing space
			$fullDataSourceName = substr($fullDataSourceName,0,-1);

			$dataSourceNameStringArray = array_merge($dataSourceNameStringArray,array($fullDataSourceName));
        }

        return $dataSourceNameStringArray;
    }

    /**
     * Get latest usage statistics as JSON
     *
     * @param  string  Time resolution
     *
     * @return str  JSON
     */
    public function getUsageStatistics($resolution, $dateStart = null, $dateEnd = null) {
		require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
		require_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';

		$redis = new Redis();
		$redis->connect(HV_REDIS_HOST,HV_REDIS_PORT);

        // Determine time intervals to query
        $interval = $this->_getQueryIntervals($resolution, $dateStart, $dateEnd);

        // Array to keep track of counts for each action
        $counts = array(
            "buildMovie"           			=> array(),
            "getClosestData"       			=> array(),
            "getClosestImage"      			=> array(),
            "getJPX"               			=> array(),
            "getJPXClosestToMidPoint" 		=> array(),
            "takeScreenshot"       			=> array(),
            "uploadMovieToYouTube" 			=> array(),
			"embed"                			=> array(),
			"minimal"						=> array(),
			"standard"						=> array(),
			"sciScript-SSWIDL"				=> array(),
			"sciScript-SunPy"				=> array(),
			"movie-notifications-granted"	=> array(),
			"movie-notifications-denied"	=> array(),
			"getJP2Image-web"				=> array(),
			"getJP2Image-jpip" 				=> array(),
			"getRandomSeed"					=> array(),
			"totalRequests"					=> array()
        );

        // Summary array
        $summary = array(
            "buildMovie"           			=> 0,
            "getClosestData"       			=> 0,
            "getClosestImage"      			=> 0,
            "getJPX"               			=> 0,
            "getJPXClosestToMidPoint"   	=> 0,
            "takeScreenshot"       			=> 0,
            "uploadMovieToYouTube" 			=> 0,
			"embed"                			=> 0,
			"minimal"						=> 0,
			"standard"						=> 0,
			"sciScript-SSWIDL"				=> 0,
			"sciScript-SunPy"				=> 0,
			"movie-notifications-granted"	=> 0,
			"movie-notifications-denied"	=> 0,
			"getJP2Image-web"				=> 0,
			"getJP2Image-jpip" 				=> 0,
			"getRandomSeed"					=> 0,
			"totalRequests"					=> 0
		);

		$new_counts = $this->_createCountsArray();
		$new_summary = $this->_createSummaryArray();
		//final counts summary
		$movieCommonSources = array();
		$movieLayerCount = array();
		//intermediate array used for keeping track of "seen" occurences
		$rawMovieSourceBreakdown = array();
		$rawMovieLayerCount = array();
		//final counts summary
		$screenshotCommonSources = array();
		$screenshotLayerCount = array();
		//intermediate array used for keeping track of "seen" occurences
		$rawScreenshotSourceBreakdown = array();
		$rawScreenshotLayerCount = array();
		//cache the data source strings
		$dataSourceNames = $this->_getDataSourceStrings();

        // Format to use for displaying dates
        $dateFormat = $this->_getDateFormat($resolution);

        // Start date
		$date = $interval['startDate'];
		$intervalStartDate = toMySQLDateString($interval['startDate']);
		$intervalEndDate = "";

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
			foreach ($new_counts as $action => $arr) {
				array_push($new_counts[$action], array($dateIndex => 0));
            }
			$dateEnd = toMySQLDateString($date);
			$intervalEndDate = $dateEnd;

			//begin statistics table data gathering
			/*
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
			*/

			//redis table statistics gathering for total number of calls
            $sql = sprintf(
				"SELECT SUM(count) AS count "
			  . "FROM redis_stats "
			  . "WHERE "
			  .     "datetime >= '%s' AND datetime < '%s'; ",
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
				
                $new_counts['total'][$i][$dateIndex] = $num;
                $new_summary['total'] += $num;
			}

			//additional real-time redis stats for total number of calls
			$statisticsKeys = $redis->keys(HV_REDIS_STATS_PREFIX . "/*");
			$statisticsData = array();
			$realTimeRedisCount = 0;
			foreach( $statisticsKeys as $key ){
				$count = (int)$redis->get($key);
				$keyComponents = explode("/",$key);
				//collect data that falls within the interval
				$keyDateTime = $keyComponents[1];
				if($keyDateTime >= $dateStart && $keyDateTime < $dateEnd){
					$realTimeRedisCount += $count;
				}
			}
			if($realTimeRedisCount > 0){
				$new_counts['total'][$i][$dateIndex] += $realTimeRedisCount;
				$new_summary['total'] += $realTimeRedisCount;
			}

			//new redis-stats statistics gathering for all api endpoints
            $sql = sprintf(
				"SELECT action, SUM(count) AS count "
			  . "FROM redis_stats "
			  . "WHERE "
			  .     "datetime >= '%s' AND datetime < '%s'"
			  ."GROUP BY action; ",
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
				
                $new_counts[$count['action']][$i][$dateIndex] = $num;
                $new_summary[$count['action']] += $num;
			}

			//additional real-time redis stats for all api endpoints
			$statisticsKeys = $redis->keys(HV_REDIS_STATS_PREFIX . "/*");
			$statisticsData = array();
			foreach( $statisticsKeys as $key ){
				$realTimeRedisCount = 0;
				$count = (int)$redis->get($key);
				$keyComponents = explode("/",$key);
				//collect data that falls within the interval
				$keyDateTime = $keyComponents[1];
				$keyAction = $keyComponents[2];
				if($keyDateTime >= $dateStart && $keyDateTime < $dateEnd){
					$realTimeRedisCount = $count;
				}
				if($realTimeRedisCount > 0){
					if(isset($new_counts[$keyAction][$i][$dateIndex])){
						$new_counts[$keyAction][$i][$dateIndex] += $realTimeRedisCount;
					}else{
						$new_counts[$keyAction][$i][$dateIndex] = $realTimeRedisCount;
					}
					$new_summary[$keyAction] += $realTimeRedisCount;
				}	
			}

			if($resolution == 'hourly' || $resolution == 'daily' || $resolution == 'weekly'){

				// Begin movie source breakdown summary section

				$sqlScreenshots = sprintf(
					"SELECT dataSourceString "
				. "FROM movies "
				. "WHERE "
				.     "timestamp BETWEEN '%s' AND '%s' ;",
				$this->_dbConnection->link->real_escape_string($dateStart),
				$this->_dbConnection->link->real_escape_string($dateEnd)
				);

				try {
					$resultScreenshots = $this->_dbConnection->query($sqlScreenshots);
				}
				catch (Exception $e) {
					return false;
				}

				//fetch data source string
				while ($row = $resultScreenshots->fetch_array(MYSQLI_ASSOC)) {
					$layerString = (string)$row['dataSourceString'];
					//split the data source string into individual data sources
					$layerStringArray = explode('],[', substr($layerString, 1, -1));
					//determine number of datasources used 
					$layerCount = sizeof($layerStringArray);
					$layerCountString = (string)$layerCount;
					if(!in_array($layerCount,$rawMovieLayerCount)){
						array_push($rawMovieLayerCount,$layerCount);
						$layerCountString = (string)$layerCount;
						$movieLayerCount[$layerCountString] = 1;
					}else{
						$movieLayerCount[$layerCountString] = $movieLayerCount[$layerCountString] + 1;
					}

					//determine counts for each datasource used in screenshots
					foreach($layerStringArray as $singleLayerString){
						$layerStringWithSpaces = str_replace(',',' ',$singleLayerString);
						foreach($dataSourceNames as $dataSource){
							if(strpos($layerStringWithSpaces,$dataSource)===0){//data source matches one of the data sources retrieved at the start
								if(!in_array($dataSource,$rawMovieSourceBreakdown)){//first time this data source is seen
									array_push($rawMovieSourceBreakdown,$dataSource);
									$movieCommonSources[$dataSource] = 1;
								}else{
									$movieCommonSources[$dataSource] = $movieCommonSources[$dataSource]+1;
								}
								break;
							}
						}
					}
				}

				// Begin screenshot source breakdown summary section

				$sqlScreenshots = sprintf(
					"SELECT dataSourceString "
				. "FROM screenshots "
				. "WHERE "
				.     "timestamp BETWEEN '%s' AND '%s' ;",
				$this->_dbConnection->link->real_escape_string($dateStart),
				$this->_dbConnection->link->real_escape_string($dateEnd)
				);

				try {
					$resultScreenshots = $this->_dbConnection->query($sqlScreenshots);
				}
				catch (Exception $e) {
					return false;
				}

				//fetch data source string
				while ($row = $resultScreenshots->fetch_array(MYSQLI_ASSOC)) {
					$layerString = (string)$row['dataSourceString'];
					//split the data source string into individual data sources
					$layerStringArray = explode('],[', substr($layerString, 1, -1));
					//determine number of datasources used 
					$layerCount = sizeof($layerStringArray);
					$layerCountString = (string)$layerCount;
					if(!in_array($layerCount,$rawScreenshotLayerCount)){
						array_push($rawScreenshotLayerCount,$layerCount);
						$layerCountString = (string)$layerCount;
						$screenshotLayerCount[$layerCountString] = 1;
					}else{
						$screenshotLayerCount[$layerCountString] = $screenshotLayerCount[$layerCountString] + 1;
					}
					//determine counts for each datasource used in screenshots
					foreach($layerStringArray as $singleLayerString){
						$layerStringWithSpaces = str_replace(',',' ',$singleLayerString);
						//foreach($dataSourceNames as $dataSource){
							//if(strpos($layerStringWithSpaces,$dataSource)===0){//data source matches one of the data sources retrieved at the start
								if(!in_array($layerStringWithSpaces,$rawScreenshotSourceBreakdown)){//first time this data source is seen
									array_push($rawScreenshotSourceBreakdown,$layerStringWithSpaces);
									$screenshotCommonSources[$layerStringWithSpaces] = 1;
								}else{
									$screenshotCommonSources[$layerStringWithSpaces] = $screenshotCommonSources[$layerStringWithSpaces]+1;
								}
						//	}
						//}
					}
				}
			}

			// Include movie source breakdown
			$counts['movieCommonSources'] = $movieCommonSources;
			$counts['movieLayerCount'] = $movieLayerCount;
			// Include screenshot source breakdown
			$counts['screenshotCommonSources'] = $screenshotCommonSources;
			$counts['screenshotLayerCount'] = $screenshotLayerCount;


			// Include movie source breakdown
			$new_counts['movieCommonSources'] = $movieCommonSources;
			$new_counts['movieLayerCount'] = $movieLayerCount;
			// Include screenshot source breakdown
			$new_counts['screenshotCommonSources'] = $screenshotCommonSources;
			$new_counts['screenshotLayerCount'] = $screenshotLayerCount;
		}

		//Get rate limit exceeded date
		$sql = sprintf(
			"SELECT identifier, SUM(count) AS count "
		  . "FROM rate_limit_exceeded "
		  . "WHERE "
		  .     "datetime >= '%s' AND datetime < '%s'"
		  . "GROUP BY identifier "
		  . "ORDER BY count DESC "
		  . "LIMIT 10; ",
		  $this->_dbConnection->link->real_escape_string($intervalStartDate),
		  $this->_dbConnection->link->real_escape_string($intervalEndDate)
		 );
		try {
			$result = $this->_dbConnection->query($sql);
		}
		catch (Exception $e) {
			return false;
		}

		// Append counts for each API action during that interval
		// to the appropriate array
		$id = 1;
		$rateLimitExceeded = array();
		while ($exceededArray = $result->fetch_array(MYSQLI_ASSOC)) {
			$num = (int)$exceededArray['count'];
			
			array_push($rateLimitExceeded, (object)array((string)$id => $num));
			$new_summary['rate_limit_exceeded'] += $num;
			$id++;
		}

        // Include summary info
		$counts['summary'] = $summary;
		$new_counts['summary'] = $new_summary;
		$new_counts['rate_limit_exceeded'] = $rateLimitExceeded;
		
        return json_encode($new_counts);
	}
	
	private function _createCountsArray(){
		return array(
			"total"						=> array(),
			'downloadScreenshot'     	=> array(),
			'getClosestImage'        	=> array(),
			'getDataSources'         	=> array(),
			'getJP2Header'           	=> array(),
			'getNewsFeed'            	=> array(),
			'getStatus'              	=> array(),
			'getSciDataScript'       	=> array(),
			'getTile'                	=> array(),
			'getUsageStatistics'     	=> array(),
			'getDataCoverageTimeline'	=> array(),
			'getDataCoverage'        	=> array(),
			'updateDataCoverage'     	=> array(),
			'shortenURL'             	=> array(),
			'takeScreenshot'         	=> array(),
			'getRandomSeed'             => array(),
			'getJP2Image'            	=> array(),
			'getJPX'                 	=> array(),
			'getJPXClosestToMidPoint'	=> array(),
			'launchJHelioviewer'     	=> array(),
			'downloadMovie'          	=> array(),
			'getMovieStatus'         	=> array(),
			'playMovie'              	=> array(),
			'queueMovie'             	=> array(),
			'reQueueMovie'           	=> array(),
			'uploadMovieToYouTube'   	=> array(),
			'checkYouTubeAuth'       	=> array(),
			'getYouTubeAuth'         	=> array(),
			'getUserVideos'          	=> array(),
			'getObservationDateVideos'	=> array(),
			'getEventFRMs'           	=> array(),
			'getEvent'		         	=> array(),
			'getFRMs'                	=> array(),
			'getDefaultEventTypes'   	=> array(),
			'getEvents'              	=> array(),
			'importEvents'           	=> array(),
			'getEventsByEventLayers' 	=> array(),
			'getEventGlossary'       	=> array(),
			'getSolarBodiesGlossary'    => array(),
			'getSolarBodies'            => array(),
			'getTrajectoryTime'         => array(),
			'logNotificationStatistics' => array(),
			'getTexture'                => array(),
			'getGeometryServiceData'    => array(),
			'buildMovie'				=> array(),//this one happens in HelioviewerMovie.php
			"getClosestData"				=> array(),
			"embed"                			=> array(),
			"minimal"						=> array(),
			"standard"						=> array(),
			"sciScript-SSWIDL"				=> array(),
			"sciScript-SunPy"				=> array(),
			"movie-notifications-granted"	=> array(),
			"movie-notifications-denied"	=> array(),
			"getJP2Image-web"				=> array(),
			"getJP2Image-jpip" 				=> array()
		);
	}

	private function _createSummaryArray(){
        return array(
			"total"						=> 0,
			'downloadScreenshot'     	=> 0,
			'getClosestImage'        	=> 0,
			'getDataSources'         	=> 0,
			'getJP2Header'           	=> 0,
			'getNewsFeed'            	=> 0,
			'getStatus'              	=> 0,
			'getSciDataScript'       	=> 0,
			'getTile'                	=> 0,
			'getUsageStatistics'     	=> 0,
			'getDataCoverageTimeline'	=> 0,
			'getDataCoverage'        	=> 0,
			'updateDataCoverage'     	=> 0,
			'shortenURL'             	=> 0,
			'takeScreenshot'         	=> 0,
			'getRandomSeed'             => 0,
			'getJP2Image'            	=> 0,
			'getJPX'                 	=> 0,
			'getJPXClosestToMidPoint'	=> 0,
			'launchJHelioviewer'     	=> 0,
			'downloadMovie'          	=> 0,
			'getMovieStatus'         	=> 0,
			'playMovie'              	=> 0,
			'queueMovie'             	=> 0,
			'reQueueMovie'           	=> 0,
			'uploadMovieToYouTube'   	=> 0,
			'checkYouTubeAuth'       	=> 0,
			'getYouTubeAuth'         	=> 0,
			'getUserVideos'          	=> 0,
			'getObservationDateVideos'	=> 0,
			'getEventFRMs'           	=> 0,
			'getEvent'		         	=> 0,
			'getFRMs'                	=> 0,
			'getDefaultEventTypes'   	=> 0,
			'getEvents'              	=> 0,
			'importEvents'           	=> 0,
			'getEventsByEventLayers' 	=> 0,
			'getEventGlossary'       	=> 0,
			'getSolarBodiesGlossary'    => 0,
			'getSolarBodies'            => 0,
			'getTrajectoryTime'         => 0,
			'logNotificationStatistics' => 0,
			'getTexture'                => 0,
			'getGeometryServiceData'    => 0,
			'buildMovie'				=> 0,//this one happens in HelioviewerMovie.php
			"getClosestData"				=> 0,
			"embed"                			=> 0,
			"minimal"						=> 0,
			"standard"						=> 0,
			"sciScript-SSWIDL"				=> 0,
			"sciScript-SunPy"				=> 0,
			"movie-notifications-granted"	=> 0,
			"movie-notifications-denied"	=> 0,
			"getJP2Image-web"				=> 0,
			"getJP2Image-jpip" 				=> 0,
			"rate_limit_exceeded"			=> 0
		);
	}

    /**
     * Return date format string for the specified time resolution
     *
     * @param  string  $resolution  Time resolution string
     *
     * @return string  Date format string
     */
    public function getDataCoverageTimeline($resolution, $endDate, $interval, $stepSize, $steps) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

        $sql = 'SELECT id, name, description FROM datasources WHERE id < 10000 ORDER BY description';
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
    public function getDataCoverage($layers, $resolution, $startDate, $endDate) {

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
			
		if(!$layers){
			return json_encode(array());
		}
		
		$layersArray = array();
		$layersKeys = array();
		$layersKeysSelect = array();
		$layersCount = 0;
		foreach($layers->toArray() as $layer){
			$sourceId = $layer['sourceId'];
			
            $sources[$layersCount] = new stdClass;
            $sources[$layersCount]->sourceId = $sourceId;
            $sources[$layersCount]->name = (isset($layer['uiLabels'][0]['name']) ? $layer['uiLabels'][0]['name'] : '').' '
            								.(isset($layer['uiLabels'][1]['name']) ? $layer['uiLabels'][1]['name'] : '').' '
            								.(isset($layer['uiLabels'][2]['name']) ? $layer['uiLabels'][2]['name'] : '').' '
            								.(isset($layer['uiLabels'][3]['name']) ? $layer['uiLabels'][3]['name'] : '').' '
            								.(isset($layer['uiLabels'][4]['name']) ? $layer['uiLabels'][4]['name'] : '');
            $sources[$layersCount]->data = array();
            
	        //$sources[$layersCount]->data[] = array($startDate->getTimestamp()*1000, null);
            
            $layersKeys[$sourceId] = $layersCount;
            
            if($sourceId < 10000){
	           $layersArray[] = $sourceId; 
	           $layersKeysSelect[$sourceId] = $layersCount;
            }else{
	            $sql = sprintf('SELECT sourceIdGroup FROM datasources WHERE id = %d LIMIT 1;', (int)$sourceId);

	        	$result = $this->_dbConnection->query($sql);
	        	$resultIds = $result->fetch_array(MYSQLI_ASSOC);
	        	$layersSubArray = explode(',', $resultIds['sourceIdGroup']);   
	        	foreach($layersSubArray as $k=>$v){
		        	$layersArray[] = $v;
		        	$layersKeysSelect[$v] = $layersCount;
	        	}
            }
            
            $layersCount++;
        }

		$layersString = ' sourceId IN ('.implode(',', $layersArray).') ';
		
		switch ($resolution) {
	        case 'm':
	            $sql = 'SELECT date AS time,
				       COUNT(*) AS count,
				       sourceId
				FROM data
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				
	            break;
	        case '5m':
	            $sql = 'SELECT 
	            		FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / 300)) * 300) as time,
						COUNT(*) AS count,
						sourceId
				FROM data
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, DATE(time)
				ORDER BY DATE(time);';
				
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 day');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'W':
	            $weekTimestamp = 7 * 24 * 60 * 60;
	            $sql = 'SELECT 
	            		DATE(DATE_FORMAT(DATE_SUB(`date`, INTERVAL DAYOFWEEK(`date`)-2 DAY), "%Y-%m-%d")) as time,
						COUNT(*) AS count,
						sourceId
				FROM data_coverage_30_min
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY sourceId, time
				ORDER BY time;';
				//DATE(DATE_FORMAT(DATE_SUB(`date`, INTERVAL DAYOFWEEK(`date`)-1 DAY), "%Y-%m-%d")) as time,
	            		//FROM_UNIXTIME(floor((UNIX_TIMESTAMP(date) / '.$weekTimestamp.')) * '.$weekTimestamp.') as time,
				//echo $sql;die;
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', strtotime('Last Monday', $startTimestamp)));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 week');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;     
	        case 'M':
	        	$sql = 'SELECT DATE(DATE_FORMAT(date, "%Y-%m-01")) AS time,
				       SUM(count) AS count,
				       sourceId
				FROM data_coverage_30_min
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
				WHERE '.$layersString.' AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
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
            $key = $layersKeysSelect[$sourceId];//echo $key.' | ';
            if($resolution == 'm'){
	            $sources[$key]->data[] = array( (strtotime($row['time'])* 1000) + $key , $key+1);
            }else{
	            if(!isset($dbData[$key][strtotime($row['time'])])){
		            $dbData[$key][strtotime($row['time'])] = $num;
	            }else{
		            $dbData[$key][strtotime($row['time'])] += $num;
	            }
	            
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

    /**
     * Gets latest datasource coverage and return as JSON
     */
    public function getDataCoverageEvents($events, $resolution, $startDate, $endDate, $currentDate) {

        require_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
		
		$distance = $endDate->getTimestamp() - $startDate->getTimestamp();
		$interval = new DateInterval('PT'.$distance.'S');
		
		$visibleStartTimestamp = $startDate->getTimestamp();
		$visibleEndTimestamp = $endDate->getTimestamp();
		
		$startDate->modify('-'.$distance.' seconds');
		$endDate->modify('+'.$distance.' seconds');
		
		$dateStart = toMySQLDateString($startDate);
		$dateEnd = toMySQLDateString($endDate);
		
		$startTimestamp = $startDate->getTimestamp();
		$endTimestamp = $endDate->getTimestamp();
		$currentTimestamp = $currentDate->getTimestamp();
		
		$dateStartISO = str_replace("Z", "", toISOString($startDate));
		$dateEndISO = str_replace("Z", "", toISOString($endDate));
		
        $sources = array();
			
		if(!$events){
			return json_encode(array());
		}
		
		$eventsKeys = array(
			'AR' => 0,
			'CC' => 1,
			'CD' => 2,
			'CH' => 3,
			'CJ' => 4,
			'CE' => 5,
			'CR' => 6,
			'CW' => 7,
			'EF' => 8,
			'ER' => 9,
			'FI' => 10,
			'FA' => 11,
			'FE' => 12,
			'FL' => 13,
			'LP' => 14,
			'OS' => 15,
			'PG' => 16,
			'SG' => 17,
			'SP' => 18,
			'SS' => 19,
			//unused
			'OT' => 20,
			'NR' => 21,
			'TO' => 22,
			'HY' => 23,
			'BO' => 24,
			'EE' => 25,
			'PB' => 26,
			'PT' => 27,
			'UNK' => 28
		);
		
		$eventsColors = array(
			'AR' => '#ff8f97',
			'CE' => '#ffb294',
			'CME' => '#ffb294',
			'CD' => '#ffd391',
			'CH' => '#fef38e',
			'CW' => '#ebff8c',
			'FI' => '#c8ff8d',
			'FE' => '#a3ff8d',
			'FA' => '#7bff8e',
			'FL' => '#7affae',
			'LP' => '#7cffc9',
			'OS' => '#81fffc',
			'SS' => '#8ce6ff',
			'EF' => '#95c6ff',
			'CJ' => '#9da4ff',
			'PG' => '#ab8cff',
			'OT' => '#d4d4d4',
			'NR' => '#d4d4d4',
			'SG' => '#e986ff',
			'SP' => '#ff82ff',
			'CR' => '#ff85ff',
			'CC' => '#ff8acc',
			'ER' => '#ff8dad',
			'TO' => '#ca89ff',
			'HY' => '#00ffff',
			'BO' => '#a7e417',
			'EE' => '#fec00a',
			'PB' => '#b3d5e4',
			'PT' => '#494a37',
			'UNK' => '#d4d4d4'
		);
				
		$eventTypes = array();
		$dbData = array();
		$dbVisibleData = array();
		$layersString = '';
		foreach($events->toArray() as $layer){
			
			if(!empty($layer['frm_name']) && $layer['frm_name'] != 'all'){
				$frms = explode(';', $layer['frm_name']);
				foreach($frms as $frm_name){
					if(!empty($layersString)){
						$layersString .= ' OR ';
					}
					$frm_name = str_replace('_', ' ', $frm_name);
					$layersString .= '(event_type = "'.$layer['event_type'].'" AND frm_name = "'.$frm_name.'")';
				}
			}else{
				if(!empty($layersString)){
					$layersString .= ' OR ';
				}
				$layersString .= 'event_type = "'.$layer['event_type'].'"';
			}
			
			$eventKey = $eventsKeys[ $layer['event_type'] ];
			$dbData[$eventKey] = array();
			$dbVisibleData[$eventKey] = false;
			$sources[$eventKey] = array(
				'data' => array(),
				'event_type' => $layer['event_type'],
				'res' => $resolution,
				'showInLegend' => false
			);
        }
        
		
		switch ($resolution) {
	        case 'm':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp($startTimestamp);
				$endInterval->setTimestamp($endTimestamp);
				
	            break;
	        case '5m':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 300) * 300);
				$endInterval->setTimestamp(floor($endTimestamp / 300) * 300);
				
				$interval = DateInterval::createFromDateString('5 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 300000;
				
	            break;
	        case '15m':
	            $sql = 'SELECT 
	            		*
				FROM events
				WHERE ('.$layersString.') AND (event_endtime >= "'.$dateStart.'" AND event_starttime <= "'.$dateEnd.'")
				ORDER BY event_starttime;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 900) * 900);
				$endInterval->setTimestamp(floor($endTimestamp / 900) * 900);
				
				$interval = DateInterval::createFromDateString('15 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 900000;
				
	            break;
	        case '30m':
	            $sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "30m" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
				$beginInterval = new DateTime();
				$endInterval = new DateTime();
				$beginInterval->setTimestamp(floor($startTimestamp / 1800) * 1800);
				$endInterval->setTimestamp(floor($endTimestamp / 1800) * 1800);
				
				$interval = DateInterval::createFromDateString('30 minutes');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 1800000;
				
	            break;         
	        case 'h':
	            $sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "1H" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
				$beginInterval = new DateTime(date('Y-m-d H:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d H:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 hour');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				$periodSeconds = 3600000;
				
	            break;     
	        case 'D':
	        	$sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "1D" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 day');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'W':
				$sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "1W" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
				$beginInterval = new DateTime(date('Y-m-d 00:00:00', strtotime('Last Monday', $startTimestamp)));
				$endInterval = new DateTime(date('Y-m-d 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 week');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;     
	        case 'M':
	        	$sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "1M" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
				$beginInterval = new DateTime(date('Y-m-01 00:00:00', $startTimestamp));
				$endInterval = new DateTime(date('Y-m-01 00:00:00', $endTimestamp));
				
				$interval = DateInterval::createFromDateString('1 month');
				$period = new DatePeriod($beginInterval, $interval, $endInterval);
				
	            break;
	        case 'Y':
	            $sql = 'SELECT 
	            	date,
	            	event_type,
	            	SUM(count) as count
				FROM events_coverage
				WHERE period = "1Y" AND ('.$layersString.') AND `date` BETWEEN "'.$dateStart.'" AND "'.$dateEnd.'"
				GROUP BY date, event_type
				ORDER BY date;';
				
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
				$emptyData[ ($dt->getTimestamp() * 1000) ] = 0;
			}
		}
		
		//Procceed SQL Data
		$result = $this->_dbConnection->query($sql);
		$i = 1;
		$uniqueIds = array();
		$j = 0;
		
		while ($row = $result->fetch_array(MYSQLI_ASSOC)) {
			//Event Name
			$key = $row['event_type'];
			
			$eventKey = $eventsKeys[$key];
						
			//Build data array
			if($resolution == 'm'){
				$timeStart = (strtotime($row['event_starttime'])* 1000);
				$timeEnd = (strtotime($row['event_endtime'])* 1000);
				if(($startTimestamp * 1000) > $timeStart){
					$timeStart = ($beginInterval->getTimestamp() * 1000);
				}
				if(($endTimestamp * 1000) < $timeEnd){
					$timeEnd = ($endInterval->getTimestamp() * 1000);
				}
				
				$modifier = 0;
				if($timeStart == $timeEnd){
					$modifier = round(($endTimestamp - $startTimestamp) / (3*60)) * 100;
					$startTimeToDisplay = $timeStart - $modifier;
					$timeEndToDisplay = $timeEnd + $modifier;
				}else{
					$startTimeToDisplay = $timeStart;
					$timeEndToDisplay = $timeEnd;
				}
				
				$sources[$eventKey]['data'][$j] = array(
					'x' => $startTimeToDisplay,
					'x2' => $timeEndToDisplay,
					'y' => $j,
					'kb_archivid' => $row['kb_archivid'],
					'hv_labels_formatted' => json_decode($row['hv_labels_formatted']),
					'event_type' => $row['event_type'],
					'frm_name' => $row['frm_name'],
					'frm_specificid' => $row['frm_specificid'],
					'event_peaktime' => $row['event_peaktime'],
					'event_starttime' => $row['event_starttime'],
					'event_endtime' => $row['event_endtime'],
					'concept' => $row['concept'],
					'modifier' => $modifier
				);
				
				if($timeStart == $timeEnd){
					$sources[$eventKey]['data'][$j]['zeroSeconds'] = true;
				}
				
				if($currentTimestamp >= $timeStart && $currentTimestamp <= $timeEnd){
					$sources[$eventKey]['data'][$j]['borderColor'] = '#ffffff';
				}else{
					$sources[$eventKey]['data'][$j]['color'] = $this->colourBrightness($eventsColors[ $row['event_type'] ], -0.9);
				}

				if($visibleEndTimestamp >= strtotime($row['event_starttime']) && $visibleStartTimestamp <= strtotime($row['event_endtime'])){
					$dbVisibleData[$eventKey] = true;
				}
				
				$uniqueIds[$row['frm_specificid']] = $j;
				$j++;
			}else{
				$timestamp = (strtotime($row['date'])* 1000);
				$dbData[$eventKey][$timestamp] = (int)$row['count'];
				
				if($visibleEndTimestamp >= strtotime($row['date']) && $visibleStartTimestamp <= strtotime($row['date'])){
					$dbVisibleData[$eventKey] = true;
				}
			}
			$i++;
		}

        //Fill 0 values rows
        if($resolution != 'm'){
	        foreach($dbData as $key=>$row){
		        foreach($emptyData as $timestamp=>$count){
			        if(isset($dbData[$key]) && isset($dbData[$key][ $timestamp ])){
				        $count = $dbData[$key][ $timestamp ];
			        }
			        $sources[$key]['data'][] = array($timestamp, (int)$count);
		        }
	        }
        }else{
	        ksort($sources);
	        $i = 1;
	        
			$levels = array();
	        foreach($sources as $k=>$series){
		        //loop over all the events
		        //$i = count($levels);
		        //$levels = array();
		        $data = array();

				foreach($series['data'] as $dk => $event){
				    //was this event placed in a level already?
				    $placed = false;
				    //loop through each level checking only the last event
				    foreach($levels as $row=>$events){
				        //we only need to check the last event if they are already sorted
				        $last = end($events);
				        //does the current event start after the end time of the last event in this level
				        if($event['x'] >= $last['x2']){
				            //add to this level and break out of the inner loop
				            $event['y'] = $row;
				            $levels[$row][] = $event;
				            $data[] = $event;
				            $placed = true;
				            break;
				        }
				    }
				    //if not placed in another level, add a new level
				    if(!$placed){
				        $levels[$i] = array($event);
				        $event['y'] = $i;
				        $data[] = $event;
				        $i++;
				    }
				}
				$sources[$k]['data'] = $data;
	        }
	        
        }

        //Remove not visible events
        foreach($dbVisibleData as $k => $isVisible){
	        if($isVisible){
		        $sources[$k]['showInLegend'] = true;
	        }
        }
        
        
        ksort($sources);
        $sources = array_values($sources);
        return json_encode($sources);
		
    }
    /*
	    Change the brightness of HEX color
    */
    public function colourBrightness($hex, $percent) {
		// Work out if hash given
		$hash = '';
		if (stristr($hex,'#')) {
			$hex = str_replace('#','',$hex);
			$hash = '#';
		}
		/// HEX TO RGB
		$rgb = array(hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2)));
		//// CALCULATE 
		for ($i=0; $i<3; $i++) {
			// See if brighter or darker
			if ($percent > 0) {
				// Lighter
				$rgb[$i] = round($rgb[$i] * $percent) + round(255 * (1-$percent));
			} else {
				// Darker
				$positivePercent = $percent - ($percent*2);
				$rgb[$i] = round($rgb[$i] * $positivePercent) + round(0 * (1-$positivePercent));
			}
			// In case rounding up causes us to go to 256
			if ($rgb[$i] > 255) {
				$rgb[$i] = 255;
			}
		}
		//// RBG to Hex
		$hex = '';
		for($i=0; $i < 3; $i++) {
			// Convert the decimal digit to hex
			$hexDigit = dechex($rgb[$i]);
			// Add a leading zero if necessary
			if(strlen($hexDigit) == 1) {
			$hexDigit = "0" . $hexDigit;
			}
			// Append to the hex string
			$hex .= $hexDigit;
		}
		return $hash.$hex;
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
            $eventsInterval = '-'.$magnitude.' minute';
            break;
        case 'h':
            $interval = 'INTERVAL '.$magnitude.' HOUR';
            $eventsInterval = '-'.$magnitude.' hour';
            break;
        case 'D':
            $interval = 'INTERVAL '.$magnitude.' DAY';
            $eventsInterval = '-'.$magnitude.' day';
            break;
        case 'M':
            $interval = 'INTERVAL '.$magnitude.' MONTH';
            $eventsInterval = '-'.$magnitude.' month';
            break;
        case 'Y':
            $interval = 'INTERVAL '.$magnitude.' YEAR';
            $eventsInterval = '-'.$magnitude.' year';
            break;
        default:
            $interval = 'INTERVAL 7 DAY';
            $eventsInterval = '-7 day';
        }
		
		// Update Image Data coverage
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
        
        // Update Image Data coverage for XRT
        // Require longer period to update
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
                    'date >= DATE_SUB(NOW(), INTERVAL 6 MONTH) ' .
                    ' AND sourceId >=38 AND sourceId <=74 ' .
                'GROUP BY ' .
                    'bin, ' .
                    'sourceId;';
        $result = $this->_dbConnection->query($sql);
        
		// 30m Update Events Data coverage
		$endDate 	= new DateTime(date("Y-m-d 23:59:59",time()));
		$startDate 	= new DateTime(date("Y-m-d 00:00:00",time()));
		$startDate 	= $startDate->modify($eventsInterval);
		
		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+30 MINUTE');  
			$endDateStr = $startDate->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						"30m" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}
		
		// 1hour
		$endDate 	= new DateTime(date("Y-m-d 23:59:59",time()));
		$startDate 	= new DateTime(date("Y-m-d 00:00:00",time()));
		$startDate 	= $startDate->modify($eventsInterval);
		
		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+1 HOUR');  
			$chankEndDate = clone $startDate;
			$endDateStr = $chankEndDate->modify('-1 Second')->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						"1H" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}  
		
		// 1day
		$endDate 	= new DateTime(date("Y-m-d 23:59:59",time()));
		$startDate 	= new DateTime(date("Y-m-d 00:00:00",time()));
		$startDate 	= $startDate->modify($eventsInterval);

		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+1 DAY');  
			$chankEndDate = clone $startDate;
			$endDateStr = $chankEndDate->modify('-1 Second')->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						"1D" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}
		
		// 1week		
		$startDate 	= new DateTime(date("Y-m-d 23:59:59",strtotime('next sunday')));
		$endDate 	= new DateTime(date("Y-m-d 00:00:00",time()));
		$endDate 	= $endDate->modify('-1 MONTH');

		while ($startDate > $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d 23:59:59');
			$startDate = $startDate->modify('-7 DAYS');  
			$chankEndDate = clone $startDate;
			$endDateStr = $chankEndDate->modify('+1 DAY')->format('Y-m-d 00:00:00');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$endDateStr.'" AS bin, 
						"1W" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$endDateStr.'" AND event_starttime <= "'.$startDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}
		
		// 1month
		$endDate 	= new DateTime(date("Y-m-d 23:59:59",time()));
		$startDate 	= new DateTime(date("Y-m-01 00:00:00",time()));
		$startDate 	= $startDate->modify('-2 MONTH');

		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+1 MONTH');  
			$chankEndDate = clone $startDate;
			$endDateStr = $chankEndDate->modify('-1 Second')->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						"1M" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}	
		
		// 1year
		$endDate 	= new DateTime(date("Y-m-d 23:59:59",time()));
		$startDate 	= new DateTime(date("Y-01-01 00:00:00",time()));
		$startDate = $startDate->modify('-2 YEAR');

		while ($startDate <= $endDate) {
			$chankStartDate = $startDate;
			$startDateStr = $chankStartDate->format('Y-m-d H:i:s');
			$startDate = $startDate->modify('+1 YEAR');  
			$chankEndDate = clone $startDate;
			$endDateStr = $chankEndDate->modify('-1 Second')->format('Y-m-d H:i:s');

			$sql = 'REPLACE INTO events_coverage (date, period, event_type, frm_name, count)
				SELECT 
					SQL_BIG_RESULT SQL_BUFFER_RESULT SQL_NO_CACHE
						"'.$startDateStr.'" AS bin, 
						"1Y" AS period, 
						event_type,
						frm_name,
			            COUNT(id)
			        FROM events
			        WHERE (event_endtime >= "'.$startDateStr.'" AND event_starttime <= "'.$endDateStr.'") 
			        GROUP BY bin, event_type, frm_name;';

            $result = $this->_dbConnection->query($sql);      
		}
		
		
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
			case "custom":
				return "Y-m-d H:i:s";
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
    private function _getQueryIntervals($resolution,$dateStart,$dateEnd) {

        date_default_timezone_set('UTC');

        // Variables
        $date     = new DateTime();
        $timestep = null;
        $numSteps = null;

        // For hourly resolution, keep the hours value, otherwise set to zero
		$hour = ($resolution == "hourly") ? (int) $date->format("H") : 0;
		
		if($resolution != "custom"){

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
			
		}else{
			$dateTimeDateEnd = new DateTime($dateEnd);
			$dateTimeDateStart = new DateTime($dateStart);
			$dateDiffSeconds = $dateTimeDateEnd->getTimestamp() - $dateTimeDateStart->getTimestamp();
			$numSteps = 24;
			$stepSeconds = (int)($dateDiffSeconds / $numSteps);
			$intervalString = "PT" . $stepSeconds . "S";
			$timestep = new DateInterval($intervalString);
			$date = $dateTimeDateStart;
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
