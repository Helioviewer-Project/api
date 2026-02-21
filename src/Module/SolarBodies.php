<?php

use Helioviewer\Api\Module\AbstractModule;
use Helioviewer\Api\Module\ModuleInterface;
use Helioviewer\Api\Sentry\Sentry;

/**
 * Solar Bodies Module
 *
 * Used for getting data on a set of planets and satellites as seen from a set of observers.
 * Retrieves data stored in JSON file format on the disk based on request time as a unix timestamp.
 */
class Module_SolarBodies extends AbstractModule implements ModuleInterface {

    private $_params;
    private $_options;
    private $_version;
    private $_observers;
    private $_bodies;
    private $_mods;
    private $_enabledTrajectories;
    private $_glossary;

    /**
     * Solar Bodies Module constructor
     *
     * @param mixed &$params API request parameters
     */
    public function __construct(&$params) {
        $this->_params = $params;
        $this->_options = array();
        // version number - used to reset all client cookies when this module changes significantly
        $this->_version = 3;
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
                Sentry::capture($e);
                handleError($e->getMessage(), $e->getCode());
            }
        }
    }

    public function getSolarBodiesGlossary() {
        $solarObservers = array();
        foreach($this->_observers as $observer){
            $solarBodies = array();
            foreach($this->_bodies as $body){
                //exclude "earth" from "soho"
                if(($observer !== "soho" || $body !== "earth") && ($observer !== "stereo_b" || $body !== "psp")){
                    array_push($solarBodies, $body);
                }
            }
            $newObserver = array($observer => $solarBodies);
            $solarObservers = array_merge($solarObservers, $newObserver);
        }

        $this->_glossary = array( "version" => $this->_version,
                                "observers" => $solarObservers,
                      "enabledTrajectories" => $this->_enabledTrajectories,
                                     "mods" => $this->_mods);

        $this->_printJSON(json_encode($this->_glossary));
    }

    public function getSolarBodiesGlossaryForScreenshot() {
        $solarObservers = array();
        foreach($this->_observers as $observer){
            $solarBodies = array();
            foreach($this->_bodies as $body){
                //exclude "earth" from "soho"
                if(($observer !== "soho" || $body !== "earth") && ($observer !== "stereo_b" || $body !== "psp")){
                    array_push($solarBodies, $body);
                }
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


    /**
     * Retrieves positions for trajectories of all available bodies around request time
     *
     * Input:   Time as a unix time stamp in milliseconds
     * Output:  {   labels : Array of bodies with position/distance data from perspective of observer for given input time,
     *              trajectories : Array of positions as trajectories for all bodies around a given input time }
     */
    public function getSolarBodies() {
        $requestTimeInteger = (int)$this->_params['time'];
        $glossary = $this->getSolarBodiesGlossaryForScreenshot();
        // --- start generating labels ---
        $solarObserversLabels = array();//initialize observers array
        $observers = array_keys($glossary['observers']);
        foreach($observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            $bodies = $glossary['observers'][$observer];
            foreach($bodies as $body){//cycle through each body in the list
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
        foreach($observers as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            $bodies = $glossary['observers'][$observer];
            foreach($bodies as $body){//cycle through each body in the list
                if(in_array($body,$this->_enabledTrajectories)){//if body trajectory is enabled
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
                }//end if body trajectory enabled
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

    public function getSolarBodiesLabelsForScreenshot($requestUnixTime, $selectedObserverBodies){
        $requestTimeInteger = $requestUnixTime;
        // --- start generating labels ---
        $solarObserversLabels = array();//initialize observers array

        foreach(array_keys($selectedObserverBodies) as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($selectedObserverBodies[$observer]  as $body){//cycle through each body in the list
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

    public function getSolarBodiesTrajectoriesForScreenshot($requestUnixTime,$selectedObserverBodies){
        $requestTimeInteger = $requestUnixTime;

        // --- start generating trajectories ---
        $solarObserversTrajectories = array();//initialize observers array
        foreach(array_keys($selectedObserverBodies) as $observer ){//cycle through each observer in the list
            $solarBodies = array();//init array for bodies
            foreach($selectedObserverBodies[$observer] as $body){//cycle through each body in the list
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
        set_error_handler(function ($errno, $errstr){
            //catch the warnings instead of printing them to the log
        }, E_WARNING);
        $dictionary = (array)json_decode(file_get_contents($dictionaryFilePath));
        restore_error_handler();
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
        set_error_handler(function ($errno, $errstr){
            //catch the warnings instead of printing them to the log
        }, E_WARNING);
        $dictionary = (array)json_decode(file_get_contents($dictionaryFilePath));
        restore_error_handler();
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
     * Helper function to output result as either JSON or JSONP
     *
     * @param string $json JSON object string
     * @param bool   $xml  Whether to wrap an XML response as JSONP
     * @param bool   $utf  Whether to return result as UTF-8
     *
     * @return void
     */
    public function getValidationRules(): array {
        switch( $this->_params['action'] ) {
            case 'getSolarBodies':
                $expected = array(
                    'required' => array('time'),
                    'ints' => array('time')
                );
                break;
            case 'getTrajectoryTime':
                $expected = array(
                    'required' => array('time', 'observer', 'body', 'direction'),
                    'ints' => array('time'),
                    'alphanum' => array('observer', 'body'),
                    'choices' => array('direction' => ['next', 'last'])
                );
                break;
            default:
                $expected = array();
                break;
        }
        return $expected;
    }

    /**
     * validate
     *
     * @return bool Returns true if input parameters are valid
     */
    public function validate() {
        $expected = $this->getValidationRules();

        // Check input
        if ( isset($expected) ) {

            Sentry::setContext('Helioviewer', [
                'validation_rules' => $expected
            ]);

            Validation_InputValidator::checkInput($expected, $this->_params,$this->_options);
        }

        return true;
    }
}

?>
