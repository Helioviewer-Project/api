<?php

require_once 'interface.Module.php';
/**
 * Solar Bodies Module
 * 
 * Used for getting data on a set of planets and satellites as seen from a set of observers.
 * Retrieves data stored in JSON file format on the disk based on request time as a unix timestamp.
 */
class Module_SolarBodies implements Module {

    private $_params;
    private $_options;

    /**
     * Solar Bodies Module constructor
     * 
     * @param mixed &$params API request parameters
     */
    public function __construct(&$params) {
        $this->_params = $params;
        $this->_options = array();

        // list of observers - add new observers here
        $this->_observers = array("soho","stereo_a","stereo_b");
        // list of bodies to track - add new celestial bodies or satellites here
        $this->_bodies = array("mercury","venus","earth","mars","jupiter","saturn","uranus","neptune","psp");
        // custom parameters and names - add any custom parameters that fall outside the normal dataset for planets
        // normal dataset: x, y, distance_observer_to_body_au, distance_sun_to_observer_au, distance_sun_to_body_au, behind_plane_of_sun
        $this->_mods = array("psp" => array( "name" => "Parker Solar Probe", //Name as displayed in the viewport overlay
                                              "url" => "https://www.nasa.gov/content/goddard/parker-solar-probe",//URL in the more info dialog
                                            "arrow" => True,//Whether to draw an arrow to the left of the name
                                          "metrics" => array( "speedkms" => array( "title" => "Speed", "unit" => "km/s" ) ), //Any custom metrics to add to the more info dialog
                                          "cadence" => 21600000 ) ); //6 hr cadence
        $this->_enabledTrajectories = array( "psp" ); //list of bodies from $this->_bodies for which trajectories should be sent
    }

    /**
     * execute
     *
     * @return void
     */
    public function execute() {
        if ($this->validate()) {
            try {
                $this->{$this->_params['action']}();
            }
            catch (Exception $e) {
                handleError($e->getMessage(), $e->getCode());
            }
        }
    }

    public function getSolarBodiesGlossary($local) {
        $solarObservers = array();
        foreach($this->_observers as $observer){
            $solarBodies = array();
            foreach($this->_bodies as $body){
                //exclude "earth" from "soho"
                if($observer !== "soho" || $body !== "earth"){
                    array_push($solarBodies, $body);
                }
            }
            $newObserver = array($observer => $solarBodies);
            $solarObservers = array_merge($solarObservers, $newObserver);
        }

        $this->_glossary = array( "observers" => $solarObservers,
                        "enabledTrajectories" => $this->_enabledTrajectories,
                                       "mods" => $this->_mods);

        $this->_printJSON(json_encode($this->_glossary));
    }

    public function getSolarBodiesGlossaryForScreenshot() {
        $solarObservers = array();
        foreach($this->_observers as $observer){
            $solarBodies = array();
            foreach($this->_bodies as $body){
                array_push($solarBodies, $body);
            }
            $newObserver = array($observer => $solarBodies);
            $solarObservers = array_merge($solarObservers, $newObserver);
        }

        $this->_glossary = array( "observers" => $solarObservers,
                        "enabledTrajectories" => $this->_enabledTrajectories,
                                       "mods" => $this->_mods);

        return $this->_glossary;
    }

    public function getTrajectoryTime(){
        $requestTimeInteger = (int)$this->_params['time'];
        $observer = $this->_params['observer'];
        $body = $this->_params['body'];
        $direction = $this->_params['direction'];//'next' or 'last'
        echo( $this->_findTrajectoryTime($requestTimeInteger, $observer, $body, $direction) );
    }

    public function getSolarBodies() {
        $requestTimeInteger = (int)$this->_params['time'];
        // --- start generating labels ---
        $solarObserversLabels = array();//initialize observers array

        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);//generate filename
                $bodyData = null;
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $this->_searchNearestTime($requestTimeInteger, $file, $observer, $body);
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                $newBody = array(//set up a key value pair
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversLabels = array_merge($solarObserversLabels,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating labels ---

        
        // --- start generating trajectories ---
        $solarObserversTrajectories = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_enabledTrajectories as $body){//cycle through each body in the list
                $newBody = array();//initialize array for body
                $newTimes = array();//initialize array for times
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);
                $bodyData = array();
                
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $file->{$observer}->{$body};
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                
                $newBody = array(
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversTrajectories = array_merge($solarObserversTrajectories,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating trajectories ---
        
        $solarObservers = array(
            "labels"        => $solarObserversLabels,
            "trajectories"  => $solarObserversTrajectories
        );

        $this->_printJSON(json_encode($solarObservers));//send the response
    }

    public function getSolarBodiesLabels(){
        $requestTimeInteger = (int)$this->_params['time'];
        // --- start generating labels ---
        $solarObserversLabels = array();//initialize observers array

        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);//generate filename
                $bodyData = null;
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $this->_searchNearestTime($requestTimeInteger, $file, $observer, $body);
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                $newBody = array(//set up a key value pair
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversLabels = array_merge($solarObserversLabels,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating labels ---
        $solarObservers = array(
            "labels"        => $solarObserversLabels
        );

        $this->_printJSON(json_encode($solarObservers));//send the response
    }

    public function getSolarBodiesLabelsForScreenshot($requestUnixTime){
        $requestTimeInteger = $requestUnixTime;
        // --- start generating labels ---
        $solarObserversLabels = array();//initialize observers array

        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);//generate filename
                $bodyData = null;
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $this->_searchNearestTime($requestTimeInteger, $file, $observer, $body);
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                $newBody = array(//set up a key value pair
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversLabels = array_merge($solarObserversLabels,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating labels ---
        $solarObservers = array(
            "labels"        => $solarObserversLabels
        );
        return $solarObservers;
    }

    public function getSolarBodiesTrajectoriesForScreenshot($requestUnixTime){
        $requestTimeInteger = $requestUnixTime;
        
        // --- start generating trajectories ---
        $solarObserversTrajectories = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $newBody = array();//initialize array for body
                $newTimes = array();//initialize array for times
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);
                $bodyData = array();
                
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $file->{$observer}->{$body};
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                
                $newBody = array(
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversTrajectories = array_merge($solarObserversTrajectories,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating trajectories ---
        
        $solarObservers = array(
            "trajectories"  => $solarObserversTrajectories
        );

        return $solarObservers;
    }

    public function getSolarBodiesTrajectories(){
        $requestTimeInteger = (int)$this->_params['time'];
        // --- start generating trajectories ---
        $solarObserversTrajectories = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $newBody = array();//initialize array for body
                //$polyline = "";
                $newTimes = array();//initialize array for times
                $filePath = $this->_findFile($requestTimeInteger, $observer, $body);
                $bodyData = array();
                if($filePath != null){
                    try{
                        $file = json_decode(file_get_contents($filePath));//open, read, and parse the file as an object
                        $bodyData = $file->{$observer}->{$body};
                    }catch (Exception $e){
                        //file does not exit
                    }    
                }
                $newBody = array(
                    $body => $bodyData
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversTrajectories = array_merge($solarObserversTrajectories,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating trajectories ---

        $solarObservers = array(
            "trajectories"  => $solarObserversTrajectories
        );

        $this->_printJSON(json_encode($solarObservers));//send the response
    }

    //finds nearest time to the request time in a given file
    //TODO: make use of binary search instead of linear search
    public function _searchNearestTime( $requestTime, $file, $observer, $body){
        $nearestTimeData = null;
        $times = array_keys((array)($file->{$observer}->{$body}));
        $closestDifference = $requestTime;
        $closestTime = null;
        foreach($times as $time){
            $timeDifference = abs($requestTime - $time);
            if($timeDifference < $closestDifference){
                $closestDifference = $timeDifference;
                $closestTime = $time;
            }
        }
        $closestData = $file->{$observer}->{$body}->{$closestTime};
        /*foreach($file as $time=>$data){

        }*/
        return $closestData;
    }

    // finds nearest time to the request time in a given file
    // TODO: consider a request time between transits (after end but before next start)
    public function _findTrajectoryTime( $requestTime, $observer, $body, $direction){
        //construct dictionary filename
        $pathToDir = HV_ROOT_DIR . '/resources/JSON/celestial-objects';
        $dictionaryFileName = $observer . '_' . $body . '_dictionary.json';
        $dictionaryFilePath = $pathToDir . '/' . $observer . '/' . $body . '/' . $dictionaryFileName;
        $dictionary = null;
        try{
            $dictionary = (array)json_decode(file_get_contents($dictionaryFilePath));
        }catch (Exception $e){
            //dictionary doesn't exist
        }
        //find a file which contains the requested timerange
        $trajectoryTime = null;
        if($dictionary != null){
            if($direction == 'next'){//find next trajectory relative to request time
                $lastEndTime = null;
                $fileFound = false;
                foreach($dictionary as $fileName=>$fileTimes){
                    if($fileFound){
                        $trajectoryTime = (int)$fileTimes->{'start'};
                        break;
                    }else if($requestTime < (int)$fileTimes->{'start'} && $lastEndTime == null){
                        //request time earlier than dictionary start
                        $trajectoryTime = (int)$fileTimes->{'start'};
                        break;
                    }else if($requestTime < (int)$fileTimes->{'start'} && $lastEndTime != null){
                        //request time between transits
                        $trajectoryTime = (int)$fileTimes->{'start'};
                        break;
                    }else if($requestTime >= (int)$fileTimes->{'start'} && $requestTime <= (int)$fileTimes->{'end'}){
                        //request time in a transit, the next transit start will be returned.
                        $fileFound = true;
                    }else if($requestTime > $fileTimes->{'end'}){
                        //store the transit end time to compare on next iteration.
                        $lastEndTime = $fileTimes->{'end'};
                    }
                }
            }else if($direction == 'last'){//find previous trajectory relative to request time
                $lastEndTime = null;
                foreach($dictionary as $fileName=>$fileTimes){
                    if($requestTime < (int)$fileTimes->{'start'} && $lastEndTime == null){
                        //request time earlier than dictionary start
                        break;
                    }else if($requestTime < (int)$fileTimes->{'start'} && $lastEndTime != null){
                        //request time between transits
                        break;
                    }else if($requestTime >= (int)$fileTimes->{'start'} && $requestTime <= (int)$fileTimes->{'end'}){
                        //request time in a transit, return previous transit end time
                        break;
                    }else if($requestTime > (int)$fileTimes->{'end'}){
                        $lastEndTime = (int)$fileTimes->{'end'};
                    }
                }
                $trajectoryTime = $lastEndTime;
            }
        }
        $response = array('time' => $trajectoryTime);
        return $this->_printJSON(json_encode($response));
    }

    public function _findFile( $requestTime, $observer, $body ){
        //construct dictionary filename
        $pathToDir = HV_ROOT_DIR . '/resources/JSON/celestial-objects';
        $dictionaryFileName = $observer . '_' . $body . '_dictionary.json';
        $dictionaryFilePath = $pathToDir . '/' . $observer . '/' . $body . '/' . $dictionaryFileName;
        $dictionary = null;
        try{
            $dictionary = (array)json_decode(file_get_contents($dictionaryFilePath));
        }catch (Exception $e){
            //dictionary doesn't exist
        }
        //find a file which contains the requested timerange
        $fileRequested = null;
        if($dictionary != null){
            foreach($dictionary as $fileName=>$fileTimes){
                if($requestTime >= (int)$fileTimes->{'start'} && $requestTime <= (int)$fileTimes->{'end'}){
                    $fileRequested = $pathToDir . '/' . $observer . '/' . $body . '/' . $fileName;
                }
            }
        }
        return $fileRequested;
    }

    /**
     * Retrieves positions for trajectories of all available bodies around request time
     * 
     * Input:   Time as a unix time stamp in milliseconds
     * Output:  {   labels : Array of bodies with position/distance data from perspective of observer for given input time,
     *              trajectories : Array of positions as trajectories for all bodies around a given input time }
     */
    public function getSolarBodiesOld() {
        $requestTime = (int)$this->_params['time'];//url request time parameter

        // --- start generating labels ---
        $nearestTimes = $this->_nearestTime($requestTime);//use helper function to compute times
        $nearestDay = $nearestTimes['nearestDay'];//nearest day as unix epoch timestamp
        $nearest30min = $nearestTimes['nearest30min'];//nearest 30 minute interval as unix epoch timestamp
        $nearestDate = substr(date("c",$nearestDay/1000),0,10);//convert unix epoch to ISO 8601 date and remove timestamp.
        $pathToDir = '../docroot/resources/JSON/celestial-objects/';//default json file root dir
        $solarObserversLabels = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $filename = $pathToDir . $observer . '/' . $body . '/' .  $observer . '_' . $body . '_' . $nearestDate . '.json';//generate filename
                $file = json_decode(file_get_contents($filename));//open, read, and parse the file as an object
                //if($file != NULL){//file exists
                    $bodyData = $file->{$observer}->{$body}->{$nearest30min};//get the coordinates
                    //if($bodyData != NULL){//data is not null
                        $newBody = array(//set up a key value pair
                            $body => $bodyData
                        );
                        $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
                    //}//end if data not null
                //}//end if file exists
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversLabels = array_merge($solarObserversLabels,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating labels ---

        // --- start generating trajectories ---
        $trajectoryTimes = $this->_generateTrajectoryTimes($requestTime);//use helper function to compute times
        $solarObserversTrajectories = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $newBody = array();//initialize array for body
                //$polyline = "";
                $newTimes = array();//initialize array for times
                foreach($trajectoryTimes as $times){//cycle through each time in the trajectory
                    $newTime = array();
                    $nearestDay = $times['nearestDay'];//nearest day as unix epoch timestamp
                    $nearest30min = $times['nearest30min'];//nearest 30 minute interval as unix epoch timestamp
                    $requestTimeResponse = $times['requestTime'];
                    $nearestDate = substr(date("c",$nearestDay/1000),0,10);//convert unix epoch to ISO 8601 date and remove timestamp.
                    $filename = $pathToDir . $observer . '/' . $body . '/' .  $observer . '_' . $body . '_' . $nearestDate . '.json';//generate filename
                    $file = json_decode(file_get_contents($filename));//open, read, and parse the file as an object
                    if($file != NULL){//file exists
                        $timeInFile = $file->{$observer}->{$body}->{$nearest30min};
                        if($timeInFile != NULL){
                            $x = round( $file->{$observer}->{$body}->{$nearest30min}->{'x'} );//x coordinate
                            $y = round( $file->{$observer}->{$body}->{$nearest30min}->{'y'} );//y coordinate
                            $behindPlaneOfSun = $file->{$observer}->{$body}->{$nearest30min}->{'behind_plane_of_sun'};//String "True" or "False" converted to 0 or 1
                            //$polyline = $polyline.$x.",".$y." ";
                            //if($requestTimeResponse == $requestTime){//if the request is the current position in the trajectory
                                $bodyData = array(//create new data object with key value pairs
                                    'x' => $x,
                                    'y' => $y,
                                    'b' => $behindPlaneOfSun, //behind_plane_of_sun
                                    't' => $requestTimeResponse //send the request time to denote current location on trajectory
                                );
                            /*}else{
                                $bodyData = array(//create new data object with key value pairs
                                    'x' => $x,
                                    'y' => $y,
                                    'b' => $behindPlaneOfSun, //behind_plane_of_sun
                                );
                            }*/
                            $nearest30minString = strval($nearest30min);
                            $newTime = array(
                                $nearest30minString => $bodyData
                            );
                            $newTimes = array_merge($newTimes, $newTime);
                        }//end if time in file exists
                        
                    }//end if file exists
                }//end foreach times
                /*
                $polyline = substr($polyline, 0, -1);
                $newBody = array(
                    $body => array('polygon' => $polyline)
                );
                */
                $newBody = array(
                    $body => $newTimes
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObserversTrajectories = array_merge($solarObserversTrajectories,$newObserver);//add to list of all observers
        }//end foreach observers
        // --- end generating trajectories ---

        //merge arrays into one object
        $solarObservers = array(
            "labels"        => $solarObserversLabels,
            "trajectories"  => $solarObserversTrajectories
        );

        $this->_printJSON(json_encode($solarObservers));//send the response
    }

    /**
     * Retrieves positions for trajectories of all available bodies around request time
     * 
     * Input:   Time as a unix time stamp in milliseconds 
     * Output:  Array of positions as trajectories for all bodies around a given input time.
     */
    public function getTrajectoriesFixedCadence() {
        $requestTime = (int)$this->_params['time'];//url request time parameter
        $trajectoryTimes = $this->_generateTrajectoryTimes($requestTime);//use helper function to compute times
        $pathToDir = '../docroot/resources/JSON/celestial-objects/';//default json file root dir
        $solarObservers = array();//initialize observers array
        foreach($this->_observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($this->_bodies as $body){//cycle through each body in the list
                $newBody = array();//initialize array for body
                //$polyline = "";
                $newTimes = array();//initialize array for times
                foreach($trajectoryTimes as $times){//cycle through each time in the trajectory
                    $newTime = array();
                    $nearestDay = $times['nearestDay'];//nearest day as unix epoch timestamp
                    $nearest30min = $times['nearest30min'];//nearest 30 minute interval as unix epoch timestamp
                    $requestTimeResponse = $times['requestTime'];
                    $nearestDate = substr(date("c",$nearestDay/1000),0,10);//convert unix epoch to ISO 8601 date and remove timestamp.
                    $filename = $pathToDir . $observer . '/' . $body . '/' .  $observer . '_' . $body . '_' . $nearestDate . '.json';//generate filename
                    $file = json_decode(file_get_contents($filename));//open, read, and parse the file as an object
                    if($file != NULL){//file exists
                        $timeInFile = $file->{$observer}->{$body}->{$nearest30min};
                        if($timeInFile != NULL){
                            $x = round( $file->{$observer}->{$body}->{$nearest30min}->{'x'} );//x coordinate
                            $y = round( $file->{$observer}->{$body}->{$nearest30min}->{'y'} );//y coordinate
                            $behindPlaneOfSun = $file->{$observer}->{$body}->{$nearest30min}->{'behind_plane_of_sun'};//String "True" or "False" converted to 0 or 1
                            //$polyline = $polyline.$x.",".$y." ";
                            //if($requestTimeResponse == $requestTime){//if the request is the current position in the trajectory
                                $bodyData = array(//create new data object with key value pairs
                                    'x' => $x,
                                    'y' => $y,
                                    'b' => $behindPlaneOfSun, //behind_plane_of_sun
                                    't' => $requestTimeResponse //send the request time to denote current location on trajectory
                                );
                            /*}else{
                                $bodyData = array(//create new data object with key value pairs
                                    'x' => $x,
                                    'y' => $y,
                                    'b' => $behindPlaneOfSun, //behind_plane_of_sun
                                );
                            }*/
                            $nearest30minString = strval($nearest30min);
                            $newTime = array(
                                $nearest30minString => $bodyData
                            );
                            $newTimes = array_merge($newTimes, $newTime);
                        }//end if time in file exists
                        
                    }//end if file exists
                }//end foreach times
                /*
                $polyline = substr($polyline, 0, -1);
                $newBody = array(
                    $body => array('polygon' => $polyline)
                );
                */
                $newBody = array(
                    $body => $newTimes
                );
                $solarBodies = array_merge($solarBodies, $newBody);//add new body coordinates to the existing array
            }//end foreach bodies
            $newObserver = array($observer => $solarBodies);//create a key-value pair of observer-bodies 
            $solarObservers = array_merge($solarObservers,$newObserver);//add to list of all observers
        }//end foreach observers
        $this->_printJSON(json_encode($solarObservers));//send the response
    }

    /**
     * Helper function for getting the nearest Day and 30 minute interval
     * to the requested time as unix epoch timestamps
     * 
     * Input: request as unix epoch timestamp in milliseconds
     * Output: Array of nearest times as unix epoch timestamps in milliseconds
     */
    private function _nearestTime($requestTime){
        $oneDay = 86400000;//One day in milliseconds
        $overshootDay = $requestTime % $oneDay ;//time since last day interval
        $lastDay = $requestTime - $overshootDay;//last day from request as unix epoch
        $nextDay = $lastDay + $oneDay;//next day after request as unix epoch
        $fifteenMinutes = 900000;//15 minutes in milliseconds
        $thirtyMinutes = 1800000;//30 minutes in milliseconds
        $overshoot30min = $requestTime % $thirtyMinutes;//time since last 30 minute interval
        $last30min = $requestTime - $overshoot30min;//last 30 minute interval from request time
        $next30min = $last30min + $thirtyMinutes;//next 30 minute interval from request time
        if($overshoot30min <= $fifteenMinutes){//request is closer to the last 30 mins
            $nearest30min = $last30min;//nearest 30 minute interval to request
            $nearestDay = $lastDay;//nearest day to the request
        }else if($overshoot30min > $fifteenMinutes && $next30min < $nextDay ){//request time is closer to the next 30 mins and is not on the next day.
            $nearest30min = $next30min;//nearest 30 minute interval to request
            $nearestDay = $lastDay;//nearest day to the request
        }else{//request time is closer to the first value of next day.
            $nearest30min = $nextDay;//nearest 30 minute interval to request
            $nearestDay = $nextDay;//nearest day to the request
        }//end choosing closest time to request date
        return array(
            'requestTime' => $requestTime,
            'nearestDay' => $nearestDay,
            'nearest30min' => $nearest30min
        );
    }

    /**
     * Helper function which generates an array of times for use with the getTrajectories method.
     * 
     * Input: request as unix epoch timestamp in milliseconds
     * Output: Array of times for finding the datapoints of the trajectory around the request time
     */
    private function _generateTrajectoryTimes($requestTime) {
        $sixHours = 21600000; // 6 hours in milliseconds
        $timeInterval = $sixHours * 4;
        $numberOfPoints = 16; // number of points in each direction from request date. (2x + 1 total data points)
        $trajectoryTimes = array();//initialize array
        for($i = -$numberOfPoints; $i <= $numberOfPoints ; $i++){//step through points in trajectory, from the past to the future
            $pointRequestTime = $requestTime + ($i * $timeInterval);//calculate point time
            $pointNearestTime = array($this->_nearestTime($pointRequestTime));//use helper function to find nearest times
            $trajectoryTimes = array_merge($trajectoryTimes, $pointNearestTime);//add times to array
        }
        return $trajectoryTimes;
    }

    /**
     * Helper function to output result as either JSON or JSONP
     *
     * @param string $json JSON object string
     * @param bool   $xml  Whether to wrap an XML response as JSONP
     * @param bool   $utf  Whether to return result as UTF-8
     *
     * @return void
     */
    private function _printJSON($json, $xml=false, $utf=false)
    {
        // Wrap JSONP requests with callback
        if(isset($this->_params['callback'])) {
            // For XML responses, surround with quotes and remove newlines to
            // make a valid JavaScript string
            if ($xml) {
                $xmlStr = str_replace("\n", '', str_replace("'", "\'", $json));
                $json = sprintf("%s('%s')", $this->_params['callback'], $xmlStr);
            }
            else {
                $json = sprintf("%s(%s)", $this->_params['callback'], $json);
            }
        }

        // Set Content-type HTTP header
        if ($utf) {
            header('Content-type: application/json;charset=UTF-8');
        }
        else {
            header('Content-Type: application/json');
        }

        // Print result
        echo $json;
    }

    /**
     * validate
     *
     * @return bool Returns true if input parameters are valid
     */
    public function validate() {

        switch( $this->_params['action'] ) {
            default:
                break;
        }
        // Check input
        if ( isset($expected) ) {
            Validation_InputValidator::checkInput($expected, $this->_params,
                $this->_options);
        }

        return true;
    }
}

?>