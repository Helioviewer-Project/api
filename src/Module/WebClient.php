<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Helioviewer WebClient Module class definition.
 * Defines methods used by Helioviewer.org to interact with a JPEG 2000 archive.
 *
 * @category Application
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @author   Kirill Vorobyev <kirill.g.vorobyev@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once "interface.Module.php";
require_once HV_ROOT_DIR.'/../src/Validation/InputValidator.php';
require_once HV_ROOT_DIR.'/../src/Helper/ErrorHandler.php';

use Helioviewer\Api\Event\EventsStateManager;

class Module_WebClient implements Module {

    private $_params;
    private $_options;

    /**
     * Constructor
     *
     * @param mixed &$params API Request parameters, including the action name.
     *
     * @return void
     */
    public function __construct(&$params) {
        $this->_params  = $params;
        $this->_options = array();
    }

    /**
     * execute
     *
     * @return void
     */
    public function execute() {
        if ( $this->validate() ) {
            try {
                $this->{$this->_params['action']}();
            }
            catch (Exception $e) {
                logException($e, "WebClient_");
                handleError($e->getMessage(), $e->getCode());
            }
        }
    }


    /**
     * 'Opens' the requested file in the current window as an attachment,
     * which pops up the "Save file as" dialog.
     *
     * @TODO test this to make sure it works in all browsers.
     *
     * @return void
     */
    public function downloadScreenshot() {

        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';

        $imgIndex = new Database_ImgIndex();

        $info = $imgIndex->getScreenshot($this->_params['id']);

        if(!$info) {
            return $this->_sendResponse(404, "NOT FOUND", "Screenshot not found");
        }

        $layers = new Helper_HelioviewerLayers($info['dataSourceString']);

        $dir = sprintf('%s/screenshots/%s/%s/',
           HV_CACHE_DIR,
           str_replace('-', '/', substr($info['timestamp'], 0, 10) ),
           $this->_params['id']
        );

        $filename = sprintf('%s_%s.png',
            str_replace(array(':', '-', ' '), '_', $info['observationDate']),
            $layers->toString()
        );

        $filepath = $dir . $filename;

        // If screenshot is no longer cached, regenerate it.
        if ( !@file_exists($filepath) ) {

            $this->reTakeScreenshot($this->_params['id']);

            if ( !@file_exists($filepath) ) {
                $filepath = str_replace(HV_CACHE_DIR, '', $filepath);
                throw new Exception(
                    'Unable to locate the requested file: '.$filepath, 24);
            }
        }

        // Set HTTP headers
        header('Pragma: public');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        // required for certain browsers
        header('Cache-Control: private', false);
        header('Content-Disposition: attachment; filename="'.$filename.'"');
        header('Content-Transfer-Encoding: binary');
        header('Content-Length: '.@filesize($filepath));
        header('Content-type: image/png');

        echo file_get_contents($filepath);
    }

    /**
     * Finds the closest image available for a given time and datasource
     *
     * @return JSON meta information for matching image
     *
     * TODO: Combine with getJP2Image? (e.g. "&display=true")
     */
    public function getClosestImageDatesForSources() {

        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();

        $results = [];
        
        foreach($this->_params['sources'] as $sid) {
            $closestImages = $imgIndex->getClosestDataBeforeAndAfter($this->_params['date'], $sid);
            $results[$sid]['prev_date'] = $closestImages['prev_date'];
            $results[$sid]['next_date'] = $closestImages['next_date'];
        }

        // Print result
        $this->_printJSON(json_encode($results));
    }

    /**
     * Finds the closest image available for a given time and datasource
     *
     * @return JSON meta information for matching image
     *
     * TODO: Combine with getJP2Image? (e.g. "&display=true")
     */
    public function getClosestImage() {
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();

        if(isset($this->_params['switchSources']) && $this->_params['switchSources']){
            if($this->_params['sourceId'] == 13 && strtotime($this->_params['date']) < strtotime('2010-06-02 00:05:39')){$this->_params['sourceId'] = 3;}
            if($this->_params['sourceId'] == 10 && strtotime($this->_params['date']) < strtotime('2010-06-02 00:05:36')){$this->_params['sourceId'] = 0;}
            if($this->_params['sourceId'] == 11 && strtotime($this->_params['date']) < strtotime('2010-06-02 00:05:31')){$this->_params['sourceId'] = 1;}
            if($this->_params['sourceId'] == 18 && strtotime($this->_params['date']) < strtotime('2010-12-06 06:53:41')){$this->_params['sourceId'] = 7;}
            if($this->_params['sourceId'] == 19 && strtotime($this->_params['date']) < strtotime('2010-12-06 06:53:41')){$this->_params['sourceId'] = 6;}
        }

        $image = $imgIndex->getDataFromDatabase($this->_params['date'], $this->_params['sourceId']);

        // Read JPEG 2000 header
        $file   = HV_JP2_DIR.$image['filepath'].'/'.$image['filename'];
        $xmlBox = $imgIndex->extractJP2MetaInfo($file);

        // Prepare cache for tiles
        $this->_createTileCacheDir($image['filepath']);

        // Return date and id
        $response = array_merge(array(
            'id'   => $image['id'],
            'date' => $image['date'],
            'name' => $image['name']
        ), $xmlBox);

        // Print result
        $this->_printJSON(json_encode($response));
    }

    /**
     * getDataSources
     *
     * @return JSON Returns a tree representing the available data sources
     */
    public function getDataSources() {

        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $verbose = isset($this->_options['verbose']) ?
            $this->_options['verbose'] : false;

        // Work-around to enable JHelioviewer to toggle on/off a specific data
        // source or sources when doing a verbose getDataSources request.
        if ( isset($this->_options['enable']) ) {
            $enabled = explode(',', substr($this->_options['enable'], 1, -1));
        }
        else {
            $enabled = array();
        }

        $imgIndex    = new Database_ImgIndex();
        $dataSources = $imgIndex->getDataSources($verbose, $enabled);

        // Print result
        $this->_printJSON(json_encode($dataSources), false, true);
    }

    /**
     * NOTE: Add option to specify XML vs. JSON... FITS vs. Entire header?
     *
     * @return void
     */
    public function getJP2Header() {
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2ImageXMLBox.php';

        $imgIndex = new Database_ImgIndex();
        $image = $imgIndex->getImageInformation($this->_params['id']);

        $filepath = HV_JP2_DIR.$image['filepath'].'/'.$image['filename'];

        $xmlBox = new Image_JPEG2000_JP2ImageXMLBox($filepath, 'meta');

        if ( isset($this->_params['callback']) ) {
            $this->_printJSON($xmlBox->getXMLString(), true);
        }
        else {
            $xmlBox->printXMLBox();
        }
    }

    /**
     * Returns a full jpeg2000 image converted to the designated image type (png/jpg/webp)
     * extension is validated via the input validator.
     */
    public function downloadImage() {
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2Image.php';
        include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

        $imgIndex = new Database_ImgIndex();
        $image = $imgIndex->getImageInformation($this->_params['id']);
        $jp2Filepath = HV_JP2_DIR.$image['filepath'].'/'.$image['filename'];
        $jp2 = new Image_JPEG2000_JP2Image(
            $jp2Filepath, $image['width'], $image['height'], 1
        );

        // 2 = 4k
        // 4 = 2k
        // 8 = 1024
        // 16 = 512
        // 32 = 256
        $scale = intval($this->_params['scale']);
        if ($scale < 2) {
            $scale = 2;
        }
        $region = new Helper_RegionOfInterest(
            -$image['width'] / 2, -$image['height'] / 2, $image['width'] / 2, $image['height'] / 2, $this->_params['scale']);

        $filepath =  $this->_getImageCacheFilename($image['filepath'], $image['filename'], $this->_params['scale']);
        // Set the extension to the type passed in or default to png.
        $filepath .= '.' . ($this->_options['type'] ?? 'png');
        // Reference pixel offset at the original image scale
        $offsetX =   $image['refPixelX'] - ($image['width']  / 2);
        $offsetY = -($image['refPixelY'] - ($image['height'] / 2));
        // Create the tile
        $classname = $this->_getImageClass($image);
               $this->_options['date'] = $image['date'];
        $tile = new $classname(
            $jp2, $filepath, $region, $image['uiLabels'],
            $offsetX, $offsetY, $this->_options,
            $image['sunCenterOffsetParams']
        );

        // downloadImage requests are very deterministic, so make sure the client knows these are safe to cache.
        // 30days (60sec * 60min * 24hours * 30days)
        header("Cache-Control: max-age=2592000");

        // Save and display
        $tile->save();
        $tile->display();
    }

    /**
     *
     */
    private function _getImageClass($image) {
        // TODO 2011/04/18: Generalize process of choosing class to use
        //error_log(json_encode($image['uiLabels']));
        if ( count($image['uiLabels']) >= 3
          && $image['uiLabels'][1]['name'] == 'SECCHI' ) {

            if ( substr($image['uiLabels'][2]['name'], 0, 3) == 'COR' ) {
                $type = 'CORImage';
            }
            else {
                $type = strtoupper($image['uiLabels'][2]['name']).'Image';
            }
        }
        else if ($image['uiLabels'][0]['name'] == 'TRACE') {
            $type = strtoupper($image['uiLabels'][0]['name']).'Image';
        }
        else if ($image['uiLabels'][0]['name'] == 'Hinode') {
            $type = 'XRTImage';
        }
        else if (count($image['uiLabels']) >=2) {
            $type = strtoupper($image['uiLabels'][1]['name']).'Image';
        }

        include_once HV_ROOT_DIR.'/../src/Image/ImageType/'.$type.'.php';
        $classname = 'Image_ImageType_'.$type;
        return $classname;
    }

    /**
     * Builds a filename for a cached image based on scale
     * and scale
     *
     * @param string $directory  The directory containing the image
     * @param float  $filename   The filename of the image
     * @param float  $imageScale Scale of the image in arcseconds per pixel
     *
     * @return string Filepath to use when locating or creating the tile
     */
    private function _getImageCacheFilename($directory, $filename, $scale) {

        $baseDirectory = HV_CACHE_DIR.'/tiles';
        $baseFilename  = substr($filename, 0, -4);

        return sprintf(
            "%s%s/%s_full_image_%s.png", $baseDirectory, $directory,
            $baseFilename, $scale
        );
    }


    /**
     * Requests a single tile to be used in Helioviewer.org.
     *
     * TODO 2011/04/19: How much longer would it take to request tiles if
     *                  meta data was refetched from database instead of
     *                  being passed in?
     *
     * @return object The image tile
     */
    public function getTile() {
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2Image.php';
        include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

        // Tilesize
        $tileSize = 512;

        $params = $this->_params;

        // Look up image properties
        $imgIndex = new Database_ImgIndex();
        $image = $imgIndex->getImageInformation($this->_params['id']);

        //Difference Filepath
        $difference = '';
        if(isset($params['difference'])){
            if($params['difference'] == 1){
                $difference = '_running';
            }elseif($params['difference'] == 2){
                $difference = '_base';
            }
        }

        $this->_options['date']         = $image['date'];

        // Tile filepath
        $filepath =  $this->_getTileCacheFilename($image['filepath'], $image['filename'], $params['imageScale'], $params['x'], $params['y'], $difference);

        // Create directories in cache
        $this->_createTileCacheDir($image['filepath']);

        // JP2 filepath
        $jp2Filepath = HV_JP2_DIR.$image['filepath'].'/'.$image['filename'];

        // Reference pixel offset at the original image scale
        $offsetX =   $image['refPixelX'] - ($image['width']  / 2);
        $offsetY = -($image['refPixelY'] - ($image['height'] / 2));

        // Instantiate a JP2Image
        $jp2 = new Image_JPEG2000_JP2Image(
            $jp2Filepath, $image['width'], $image['height'], $image['scale']
        );

        // Region of interest
        $roi = $this->_tileCoordinatesToROI($params['x'], $params['y'], $params['imageScale'], $image['scale'], $tileSize, $offsetX, $offsetY);

        // Choose type of tile to create
        $classname = $this->_getImageClass($image);

        //Difference JP2 File
        if(isset($params['difference']) && $params['difference'] > 0){
            if($params['difference'] == 1){
                $date = new DateTime($image['date']);

                if($params['diffTime'] == 6){ $dateDiff = 'year'; }
                else if($params['diffTime'] == 5){ $dateDiff = 'month'; }
                else if($params['diffTime'] == 4){ $dateDiff = 'week'; }
                else if($params['diffTime'] == 3){ $dateDiff = 'day'; }
                else if($params['diffTime'] == 2){ $dateDiff = 'hour'; }
                else if($params['diffTime'] == 0){ $dateDiff = 'second'; }
                else{ $dateDiff = 'minute'; }

                $date->modify('-'.$params['diffCount'].' '.$dateDiff);
                $date = $this->_clampDate($date);
                $dateStr = $date->format("Y-m-d\TH:i:s.000\Z");
            }elseif($params['difference'] == 2){
                $dateStr = $params['baseDiffTime'];
            }

            //Create difference JP2 image
            $imageDifference = $imgIndex->getClosestDataBeforeDate($dateStr, $image['sourceId']);
            $fileDifference   = HV_JP2_DIR.$imageDifference['filepath'].'/'.$imageDifference['filename'];
            $jp2Difference = new Image_JPEG2000_JP2Image($fileDifference, $image['width'], $image['height'], $image['scale']);

            $this->_options['jp2DiffPath']   =  $this->_getTileCacheFilename($image['filepath'], $imageDifference['filename'], $params['imageScale'], $params['x'], $params['y'], $difference);
            $this->_options['jp2Difference'] = $jp2Difference;

        }

        // Create the tile
        $tile = new $classname(
            $jp2, $filepath, $roi, $image['uiLabels'],
            $offsetX, $offsetY, $this->_options,
            $image['sunCenterOffsetParams']
        );

        // getTile requests are very deterministic, so make sure the client knows these are safe to cache.
        // 30days (60sec * 60min * 24hours * 30days)
        header("Cache-Control: max-age=2592000");

        // Save and display
        $tile->save();
        $tile->display();
    }

    /**
     * Obtains layer information, ranges of pixels visible, and the date being
     * looked at and creates a composite image (a Screenshot) of all the
     * layers. Does it with HTTP POST request
     *
     * See the API webpage for example usage.
     *
     * @return image/jpeg or JSON
     */
    public function postScreenshot() 
    {
        include_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerScreenshot.php';
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';

        $json_params = $this->_params['json'];

        // Data Layers
        $layers = new Helper_HelioviewerLayers($json_params['layers']);

        // Event Labels
        $movieIcons = false;
        if ( array_key_exists('movieIcons', $json_params) ) {
            $movieIcons = $json_params['movieIcons'];
        }

        // Scale
        $scale     = false;
        $scaleType = 'earth';
        $scaleX    = 0;
        $scaleY    = 0;
        if ( array_key_exists('scale', $json_params) ) {
            $scale     = (isset($json_params['scale']) ? $json_params['scale'] : $scale);
            $scaleType = (isset($json_params['scaleType']) ? $json_params['scaleType'] : $scaleType);
            $scaleX    = (isset($json_params['scaleX']) ? $json_params['scaleX'] : $scaleX);
            $scaleY    = (isset($json_params['scaleY']) ? $json_params['scaleY'] : $scaleY);
        }

        // Region of interest
        $roi = $this->_getRegionOfInterest($json_params, $json_params);

        // Celestial Bodies
        if( isset($json_params['celestialBodiesLabels']) && isset($json_params['celestialBodiesTrajectories']) ){

            $celestialBodiesLabels = $json_params['celestialBodiesLabels'];
            $celestialBodiesTrajectories = $json_params['celestialBodiesTrajectories'];
            $celestialBodies = array(
                'labels'       => $celestialBodiesLabels,
                'trajectories' => $celestialBodiesTrajectories
            );

        } else {

            $celestialBodies = array( "labels" => "", "trajectories" => "");

        }

        $events_manager = EventsStateManager::buildFromEventsState($json_params['eventsState']);

        // Create the screenshot
        $screenshot = new Image_Composite_HelioviewerScreenshot(
            $layers, 
            $events_manager, 
            $movieIcons, 
            $celestialBodies, 
            $scale, 
            $scaleType, 
            $scaleX,
            $scaleY, 
            $json_params['date'], 
            $roi, 
            $json_params
        );

        // Display screenshot
        if (isset($json_params['display']) && $json_params['display']) {
            $screenshot->display();
        } else {
            // Print JSON
            $this->_printJSON(json_encode(array('id' => $screenshot->id)));
        }
    }

    /**
     * Obtains layer information, ranges of pixels visible, and the date being
     * looked at and creates a composite image (a Screenshot) of all the
     * layers.
     *
     * See the API webpage for example usage.
     *
     * Parameters quality, filename, and display are optional parameters and
     * can be left out completely.
     *
     * @return image/jpeg or JSON
     */
    public function takeScreenshot() {
        include_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerScreenshot.php';
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';

        // Data Layers
        $layers = new Helper_HelioviewerLayers($this->_params['layers']);

        // Event Labels
        $movieIcons = false;
        if ( array_key_exists('movieIcons', $this->_params) ) {
            $movieIcons = $this->_params['movieIcons'];
        }

        // Scale
        $scale     = false;
        $scaleType = 'earth';
        $scaleX    = 0;
        $scaleY    = 0;
        if ( array_key_exists('scale', $this->_params) ) {
            $scale     = (isset($this->_params['scale']) ? $this->_params['scale'] : $scale);
            $scaleType = (isset($this->_params['scaleType']) ? $this->_params['scaleType'] : $scaleType);
            $scaleX    = (isset($this->_params['scaleX']) ? $this->_params['scaleX'] : $scaleX);
            $scaleY    = (isset($this->_params['scaleY']) ? $this->_params['scaleY'] : $scaleY);
        }

        // Region of interest
        $roi = $this->_getRegionOfInterest($this->_options, $this->_params);

        // Celestial Bodies
        if( isset($this->_params['celestialBodiesLabels']) && isset($this->_params['celestialBodiesTrajectories']) ){
            $celestialBodiesLabels = $this->_params['celestialBodiesLabels'];
            $celestialBodiesTrajectories = $this->_params['celestialBodiesTrajectories'];
            $celestialBodies = array(
                'labels'       => $celestialBodiesLabels,
                'trajectories' => $celestialBodiesTrajectories
            );
        } else {
            $celestialBodies = array( 
                "labels" => "", 
                "trajectories" => ""
            );
        }

        // Event legacy string
        $events_legacy_string = "";
        if ( array_key_exists('events', $this->_params) ) {
            $events_legacy_string = $this->_params['events'];
        }

        // Event legacy labels switch
        $event_labels = false;
        if ( array_key_exists('eventLabels', $this->_params) ) {
            $event_labels = (bool)$this->_params['eventLabels'];
        }

        // Events manager built from old logic
        $events_manager = EventsStateManager::buildFromLegacyEventStrings($events_legacy_string, $event_labels);

        // Create the screenshot
        $screenshot = new Image_Composite_HelioviewerScreenshot(
            $layers, 
            $events_manager, 
            $movieIcons, 
            $celestialBodies, 
            $scale, 
            $scaleType, 
            $scaleX,
            $scaleY, 
            $this->_params['date'], 
            $roi, 
            $this->_options
        );

        // Display screenshot
        if (isset($this->_options['display']) && $this->_options['display']) {
            $screenshot->display();
        } else {
            // Print JSON
            $this->_printJSON(json_encode(array('id' => $screenshot->id)));
        }
    }

    /**
     * Re-generate a screenshot using the metadata stored in the
     * `screenshots` database table.
     *
     * @return
     */
    public function reTakeScreenshot($screenshotId) {
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        include_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerScreenshot.php';
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';
        include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

        // Default options
        $defaults = array(
            'force' => false,
            'display' => false
        );
        $options = array_replace($defaults, $this->_params);

        $screenshotId = intval($screenshotId);

        if ( $screenshotId <= 0 ) {
            throw new Exception(
                'Value of screenshot "id" parameter is invalid.', 25);
        }

        $imgIndex = new Database_ImgIndex();
        $metaData = $imgIndex->getScreenshotMetadata($screenshotId);

        $options['timestamp'] = $metaData['timestamp'];
        $options['observationDate'] = $metaData['observationDate'];

        $roiArr = explode(',', str_replace(array('POLYGON((', '))'), '',
            $metaData['roi']));

        $roi = array();
        foreach ( $roiArr as $index => $coordStr ) {
            $coordArr = explode(' ', $coordStr);
            if ( $index === 0 ) {
                $x1 = $coordArr[0];
                $y1 = $coordArr[1];
                $x2 = $coordArr[0];
                $y2 = $coordArr[1];
            }
            else if ($coordArr[0] <= $x1 &&
                     $coordArr[1] <= $y1) {
                $x1 = $coordArr[0];
                $y1 = $coordArr[1];
            }
            else if ($coordArr[0] >= $x2 &&
                     $coordArr[1] >= $y2) {
                $x2 = $coordArr[0];
                $y2 = $coordArr[1];
            }
        }

        $roi = new Helper_RegionOfInterest($x1, $y1, $x2, $y2,
            $metaData['imageScale']);

        // Data Layers
        $layers = new Helper_HelioviewerLayers(
            $metaData['dataSourceString']);

        // Limit screenshot to five layers
        if ( $layers->length() < 1 || $layers->length() > 5 ) {
            throw new Exception(
                'Invalid layer choices! You must specify 1-5 comma-separated '.
                'layer names.', 22);
        }

        // Event Layers
        $events_state_from_metadata = json_decode($metaData['eventsState'], true);
        $events_manager;

        if(!empty($events_state_from_metadata)) {
            $events_manager = EventsStateManager::buildFromEventsState($events_state_from_metadata);
        } else {
            $events_manager = EventsStateManager::buildFromLegacyEventStrings($metaData['eventSourceString'], (bool)$metaData['eventsLabels']);
        }


        $celestialBodies = array( "labels" => $metaData['celestialBodiesLabels'],
                            "trajectories" => $metaData['celestialBodiesTrajectories']);

        // Create the screenshot
        $screenshot = new Image_Composite_HelioviewerScreenshot(
            $layers, 
            $events_manager, 
            (bool)$metaData['movieIcons'],
            $celestialBodies,
            (bool)$metaData['scale'], 
            $metaData['scaleType'],
            $metaData['scaleX'], 
            $metaData['scaleY'],
            $metaData['observationDate'], 
            $roi, 
            $options
        );
    }

    /**
     * Retrieves a local or remote RSS/Atom news feed
     */
    public function getNewsFeed() {

        include_once HV_ROOT_DIR.'/../lib/JG_Cache/JG_Cache.php';

        // Create cache dir if it doesn't already exist
        $cacheDir = HV_CACHE_DIR . '/remote';
        if ( !@file_exists($cacheDir) ) {
            @mkdir($cacheDir, 0775, true);
        }

        // Check for feed in cache
        $cache = new JG_Cache($cacheDir);

        if( !($feed = $cache->get('feed.xml', 1800)) ) {

            // Re-fetch if it is old than 30 mins
            include_once HV_ROOT_DIR.'/../src/Net/Proxy.php';
            $proxy = new Net_Proxy(HV_NEWS_FEED_URL);
            $feed  = $proxy->query(array(), true);
            $cache->set('feed.xml', $feed);
        }

        // Print Response as XML or JSONP/XML
        if ( isset($this->_params['callback']) ) {
            $this->_printJSON($feed, true, true);
        }
        else {
            header('Content-Type: text/xml;charset=UTF-8');
            echo $feed;
        }
    }

    private static function GetClientUrl(): string {
        // i.e. http://helioviewer.org
        return HV_CLIENT_URL;
    }

    private static function GetApiUrl(): string {
        // i.e. http://api.helioviewer.org
        return HV_WEB_ROOT_URL;
    }

    public function goto() {
        include_once HV_ROOT_DIR . '/../src/Net/LinkShortener.php';
        $shortId = $this->_params['id'];
        $url = Net_LinkShortener::Get($shortId);
        if (strpos($url, self::GetClientUrl()) === 0) {
            header("Location: $url");
        } else {
            $base = self::GetClientUrl();
            header("Location: $base");
        }
    }

    /**
     * This function saves event state into our database
     *
     * It saves the event and returns the identifier
     */
    public function saveWebClientState() {

        require_once HV_ROOT_DIR.'/../src/Database/ClientState.php';

        $client_state = new ClientState();

        try {

            $state_key = $client_state->upsert($this->_params['json']);

            return $this->_sendResponse(200, 'OK', $state_key);

        } catch (\Exception $e) {
            return $this->_sendResponse(500, 'Server Error', '');
        }


    }

    /**
     * This function returns the event state for the given id 
     */
    public function getWebClientState() 
    {
        require_once HV_ROOT_DIR.'/../src/Database/ClientState.php';

        $client_state = new ClientState();

        try {

            $state = $client_state->find($this->_params['state_id']);

            if(is_null($state)) {
                return $this->_sendResponse(404, 'Not Found', '');
            }

            return $this->_sendResponse(200, 'OK', $state);

        } catch (\Exception $e) {

            return $this->_sendResponse(500, 'Server Error', '');

        }


    }

    /**
     * Uses bit.ly to generate a shortened URL
     *
     * Requests are sent via back-end for security per the bit.ly docs
     * recommendation.
     */
    public function shortenURL() {
        include_once HV_ROOT_DIR . '/../src/Net/LinkShortener.php';
        $allowed = false;

        if (stripos($this->_params['queryString'], self::GetClientUrl()) === 0) {
            $allowed = true;
        }

        if($allowed){
            $longURL = urldecode($this->_params['queryString']);
            $shortId = Net_LinkShortener::Create($longURL);
            $this->_printJSON(json_encode([
                "status_code" => 200,
                "status_txt" => "OK",
                "data" => [
                    "long_url" => $longURL,
                    "url" => self::GetApiUrl() . "/v2/goto/?id=$shortId",
                ]
            ]));
        } else {
            $this->_printJSON(json_encode(array(
                "status_code" => 200,
                "status_txt" => "OK",
                "data" => array(
                    "long_url" => $this->_params['queryString'],
                    "url" => $this->_params['queryString'],
                ))
            ));
        }

    }

    /**
     * Retrieves the latest usage statistics from the database
     */
    public function getUsageStatistics() {

        // Are usage stats enabled?
        if ( !HV_ENABLE_STATISTICS_COLLECTION ) {
            throw new Exception('Sorry, usage statistics are not collected ' .
                'for this site.', 26);
        }

        // Determine resolution to use
        $validResolutions = array('hourly', 'daily', 'weekly', 'monthly',
            'yearly','custom');
        if ( isset($this->_options['resolution']) ) {

            // Make sure a valid resolution was specified
            if ( !in_array($this->_options['resolution'], $validResolutions) ) {
                $msg = 'Invalid resolution specified. Valid options include '
                     . 'hourly, daily, weekly, monthly, and yearly';
                throw new Exception($msg, 25);
            }
        }
        else {
            // Default to daily
            $this->_options['resolution'] = 'daily';
        }

        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
        $statistics = new Database_Statistics();

        $this->_printJSON($statistics->getUsageStatistics(
            $this->_options['resolution'],$this->_options['dateStart'],$this->_options['dateEnd'])
        );
    }

    /**
     * Creates a SSW script to download the original data associated with
     * the specified parameters.
     */
     public function getSciDataScript()
     {
         if (strtolower($this->_params['lang']) == 'sswidl') {
             include_once HV_ROOT_DIR.'/../src/Helper/SSWIDL.php';
             $script = new Helper_SSWIDL($this->_params);
         } else if (strtolower($this->_params['lang']) == 'sunpy') {
             include_once HV_ROOT_DIR.'/../src/Helper/SunPy.php';
             $script = new Helper_SunPy($this->_params);
         } else {
             handleError('Invalid value specified for request parameter "lang".', 25);
         }

         $script->buildScript();
     }

    /**
     * Retrieves the latest usage statistics from the database
     */
    public function getDataCoverage() {
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerEvents.php';

        // Data Layers
        if(!empty($this->_options['imageLayers'])){
            $layers = new Helper_HelioviewerLayers($this->_options['imageLayers']);
        }else{
            $layers = null;
        }

        // Events Layers
        if(!empty($this->_options['eventLayers'])){
            $events = new Helper_HelioviewerEvents($this->_params['eventLayers']);
        }else{
            $events = null;
        }


        $start = @$this->_options['startDate'];
        if ($start && !preg_match('/^[0-9]+$/', $start)) {
            die("Invalid start parameter: $start");
        }
        $end = @$this->_options['endDate'];
        if ($end && !preg_match('/^[0-9]+$/', $end)) {
            die("Invalid end parameter: $end");
        }
        $current = @$this->_options['currentDate'];
        if ($current && !preg_match('/^[0-9]+$/', $current)) {
            die("Invalid end parameter: $current");
        }
        if (!$start) $start = 0;
        if (!$end) $end = time() * 1000;
        if (!$current) $current = 0;

        // set some utility variables
        $range = $end - $start;

        // find the right range
        if ($range < 105 * 60 * 1000) {
            $resolution = 'm';

        // 12 hours range loads hourly data
        } elseif  ($range < 12 * 3600 * 1000) {
            $resolution = '5m';

        // one month range loads hourly data
        } elseif  ($range < 2 * 24 * 3600 * 1000) {
            $resolution = '15m';

        // one month range loads hourly data
        } elseif ($range < 10 * 24 * 3600 * 1000) {
            $resolution = 'h';

        // one year range loads daily data
        } elseif ($range < 6 * 31 * 24 * 3600 * 1000) {
            $resolution = 'D';

        // half year range loads daily data
        } elseif ($range < 15 * 31 * 24 * 3600 * 1000) {
            $resolution = 'W';

        // greater range loads monthly data
        } else {
            $resolution = 'M';
        }
        //$resolution = 'm';

        $dateEnd = new DateTime();
        if ( isset($this->_options['endDate']) ) {
            $dateEnd->setTimestamp(intval($this->_options['endDate']/1000));
        }else{
            $dateEnd->setTimestamp(intval($end/1000));
        }
        $dateStart = new DateTime();
        if ( isset($this->_options['startDate']) ) {
            $dateStart->setTimestamp(intval($this->_options['startDate']/1000));
        }else{
            $dateStart->setTimestamp(intval($start/1000));
        }
        $dateCurrent = new DateTime();
        if ( isset($this->_options['currentDate']) ) {
            $dateCurrent->setTimestamp( $this->_options['currentDate']);
        }else{
            $dateCurrent->setTimestamp( $current);
        }

        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
        $statistics = new Database_Statistics();

        if($layers != null){
            $this->_printJSON(
                $statistics->getDataCoverage(
                    $layers,
                    $resolution,
                    $dateStart,
                    $dateEnd
                )
            );
        }else if($events != null){
            if ($range < 24 * 60 * 60 * 1000) {
                $resolution = 'm';
            }

            if($resolution == '5m' || $resolution == '15m' ){
                $resolution = '30m';
            }
            $this->_printJSON(
                $statistics->getDataCoverageEvents(
                    $events,
                    $resolution,
                    $dateStart,
                    $dateEnd,
                    $dateCurrent
                )
            );
        }


    }

    /**
     * Retrieves the latest usage statistics from the database
     */
    public function getDataCoverageTimeline() {

        // Define allowed date/time resolutions
        $validRes = array('30m',
                          '1h',
                          '1D',
                          '1W',
                          '1M', '3M',
                          '1Y');
        if ( isset($this->_options['resolution']) && $this->_options['resolution']!='') {

            // Make sure a valid resolution was specified
            if ( !in_array($this->_options['resolution'], $validRes) ) {
                $msg = 'Invalid resolution specified. Valid options include: '
                     . implode(', ', $validRes);
                throw new Exception($msg, 25);
            }
            $resolution = $this->_options['resolution'];
        }
        else {
            $resolution = '1h';
        }

        $magnitude   = intval($resolution);
        $period_abbr = ltrim($resolution, '0123456789');


        $date = false;
        if ( isset($this->_options['endDate']) ) {
            $formatArr = Array('Y-m-d\TH:i:s\Z',
                               'Y-m-d\TH:i:s.u\Z',
                               'Y-m-d\TH:i:s.\Z');
            foreach ( $formatArr as $fmt ) {
                $date = DateTime::createFromFormat(
                    $fmt, $this->_options['endDate'] );
                if ( $date !== false ) {
                    break;
                }
            }
        }
        if ( $date === false ) {
            $date = new DateTime();
        }


        switch ($period_abbr) {
        case 'm':
            $steps    = 30;
            $stepSize = new DateInterval('PT'.($magnitude).'M');
            $interval = new DateInterval('PT'.($magnitude*$steps).'M');
            $endDate = clone $date;
            $endDate->setTime(date_format($date,'H'), 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        case 'h':
            $steps    = 24;
            $stepSize = new DateInterval('PT'.($magnitude).'H');
            $interval = new DateInterval('PT'.($magnitude*$steps).'H');
            $date->setTime(date_format($date,'H'), 59, 59);
            $endDate = clone $date;
            $endDate->setTime(date_format($date,'H'), 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        case 'D':
            $steps = 30;
            $stepSize = new DateInterval('P'.($magnitude).'D');
            $interval = new DateInterval('P'.($magnitude*$steps).'D');
            $endDate = clone $date;
            $endDate->setTime(23, 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        case 'W':
            $steps = 36;
            $stepSize = new DateInterval('P'.($magnitude).'W');
            $interval = new DateInterval('P'.($magnitude*$steps).'W');
            $endDate = clone $date;
            $endDate->modify('first day of this week');
            $endDate->add(new DateInterval('P2W'));
            $endDate->setTime(23, 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        case 'M':
            $steps = 36;
            $stepSize = new DateInterval('P'.($magnitude).'M');
            $interval = new DateInterval('P'.($magnitude*$steps).'M');
            $endDate = clone $date;
            $endDate->modify('last day of this month');
            $endDate->setTime(23, 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        case 'Y':
            $steps = 25;
            $stepSize = new DateInterval('P'.($magnitude).'Y');
            $interval = new DateInterval('P'.($magnitude*$steps).'Y');
            $endDate = clone $date;
            $endDate->setDate(date_format($date,'Y'), 12, 31);
            $endDate->setTime(23, 59, 59);
            $endDate->add(new DateInterval('PT1S'));
            break;
        default:
            $msg = 'Invalid resolution specified. Valid options include: '
                 . implode(', ', $validRes);
            throw new Exception($msg, 25);
        }

        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
        $statistics = new Database_Statistics();

        $this->_printJSON(
            $statistics->getDataCoverageTimeline(
                $resolution,
                $endDate,
                $interval,
                $stepSize,
                $steps
            )
        );
    }

    /**
     * Retrieves the latest usage statistics from the database
     */
    public function updateDataCoverage() {
        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
        $statistics = new Database_Statistics();

        if ( array_key_exists('period', $this->_options) ) {
            $period = $this->_options['period'];
        }
        else {
            $period = null;
        }

        $this->_printJSON(
            $statistics->updateDataCoverage($period)
        );
    }

    /**
     * Returns status information (i.e. time of most recent available data)
     * based on either observatory, instrument, detector or measurement.
     *
     * There are two types of queries that can be made:
     *
     * (1) instrument
     *
     * If key is set to instrument, then the time of the data source associated
     * with that instrument that is lagging the furthest behind is returned.
     *
     * (2) nickname
     *
     * If the key is set to nickname, then the most recent image times
     * are returned for all datasources, sorted by instrument.
     */
     public function getStatus() {

         // Connect to database
         include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
         include_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';

         $imgIndex = new Database_ImgIndex();

         // Default to instrument-level status information
         if ( !isset($this->_options['key']) ) {
             $this->_options['key'] = 'instrument';
         }

         $statuses = array();

         // Case 1: instrument
        $instruments = $imgIndex->getDataSourcesByInstrument();

        // Date format
        $format = 'Y-m-d H:i:s';

        // Current time
        $now = new DateTime();

        // Iterate through instruments
        foreach( $instruments as $inst => $dataSources ) {

            $newest = null;
            $measurement = null;

            // Keep track of which datasource is the furthest behind
            foreach( $dataSources as $dataSource ) {

                // Get date string for most recent image
                $dateStr = $imgIndex->getNewestData($dataSource['id']);

                // Skip data source if no images are found
                if ( is_null($dateStr) ) {
                    continue;
                }

                // Convert to DateTime
                $date = DateTime::createFromFormat($format, $dateStr);

                // Store if older
                if ((is_null($newest)) || ($newest < $date)) {
                    $newest = $date;
                    $measurement = $dataSource['name'];
                }
            }

            if (!is_null($newest)) {
                // Get elapsed time
                $delta = $now->getTimestamp() - $newest->getTimestamp();

                // Add to result array
                if ( $delta > 0 ) {
                    $statuses[$inst] = array(
                        'time' => toISOString($newest),
                        'level' => $this->_computeStatusLevel($delta, $inst),
                        'secondsBehind' => $delta,
                        'measurement' => $measurement
                    );
                }
            }
        }

         // Get a list of the datasources grouped by instrument
         $this->_printJSON(json_encode($statuses));
     }

     public function logNotificationStatistics() {
        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
        $statistics = new Database_Statistics();

        $notification_status = $this->_params['notifications'];

        if($notification_status == "granted"){
            $statistics->log('movie-notifications-granted');
            $statistics->logRedis('movie-notifications-granted');
        }else if($notification_status == "denied") {
            $statistics->log('movie-notifications-denied');
            $statistics->logRedis('movie-notifications-denied');
        }
        echo $this->_printJSON(json_encode($notification_status));
     }

     /**
      * Creates a random seed for pseudorandom number generators
      * based on a hash of AIA image data combined with the current time in microseconds
      *
      * This function uses SHA-256 to hash the image and resulting strings.
      *
      * Special thanks to: Juan E. Jiménez [flybd5@gmail.com]
      * for submitting the idea and pseudocode for this function.
      */
     public function getRandomSeed() {
        // Initialize image database
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        $imgIndex = new Database_ImgIndex();

        // Current unix timestamp in nanoseconds for sourceId selection
        $nanoTime = (int)exec("date +%s%N");
        // AIA sourceId 8-14 random range based on nanoTime above
        $sourceId = $nanoTime % 7 + 8;
        // Current date in ISO 8601 format for latest image
        $requestDateTime = date("c");
        // Some formatting to JS style date that getDataFromDatabase expects like "2020-06-19T00:00:00Z"
        $apiFormattedTime = explode("+",$requestDateTime)[0] . "Z";
        // Get the newest image from database
        $image = $imgIndex->getClosestDataBeforeDate($apiFormattedTime, $sourceId);
        // Make the filepath
        $file = HV_JP2_DIR . $image['filepath'] . '/' . $image['filename'];

        // Hash the image using sha256
        $imageHash = hash_file("sha256",$file);
        // Hash request IP address
        $requestAddressHash = hash("sha256", $_SERVER["REMOTE_ADDR"]);
        // Get a new unix timestamp in nanoseconds
        $nanoTimeSeed = (int)exec("date +%s%N");
        // Concatenate current time in nanoseconds, hash of the request IP, and previous image hash, then hash again.
        $seed = hash("sha256", $nanoTimeSeed . $imageHash . $requestAddressHash);

        // Build return object
        $response = array(
            'seed' => $seed
        );

        // Output result
        $this->_printJSON(json_encode($response));
     }

    /**
     * Returns an embeddable image showing LASCO C2/C3 data leading up to
     * the selected eclipse.
     */
    public function getEclipseImage() {
        // Default to overlaying the moon in place of the sun.
        if (!isset($this->_options['moon'])) {
            $this->_options['moon'] = true;
        }
        // Get the current time
        $date = new DateTimeImmutable("now", new DateTimeZone("UTC"));
        // Get the current time as a string
        $now_str = $date->format("Y-m-d\TH:i:s\Z");

        // Data Layer for lasco C2
        include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';
        $layer_str = "[SOHO,LASCO,C2,white-light,2,100,0,60,1,$now_str]";
        $layers = new Helper_HelioviewerLayers($layer_str);

        // Get the region of interest which encapsulates LASCO C3
        include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';
        $range = 6000;
        $roi = new Helper_RegionOfInterest(-$range, -$range, $range, $range, 15);

        // Create empty events object required for screenshots.
        $events_manager = EventStateManager::buildFromLegacyEventStrings('', false);

        // Create empty celestial bodies list
        $celestialBodies = array( "labels" => "",
                                "trajectories" => "");

        // Create the base screenshot image
        include_once HV_ROOT_DIR.'/../src/Image/Composite/HelioviewerScreenshot.php';
        $screenshot = new Image_Composite_HelioviewerScreenshot(
            $layers, 
            $events_manager,
            false, 
            $celestialBodies, 
            false, 
            'earth', 
            0, 
            0, 
            $now_str, 
            $roi,
            [
                'grayscale' => true, 
                'eclipse' => true, 
                'moon' => $this->_options['moon']
            ]
        );
        $screenshot->display();
    }

    /**
     * Determines a numeric indicator ("level") of how up to date a particular
     * image source is relative to it's normal operational availability.
     *
     * Note: values are currently hard-coded for different instruments.
     * A better solution might be to
     *
     */
     private function _computeStatusLevel($elapsed, $inst) {
        // Default values
        $t1 =   7200; // 2 hrs
        $t2 =  14400; // 4 hrs
        $t3 =  43200; // 12 hrs
        $t4 = 604800; // 1 week

        // Instrument-specific thresholds
        if ($inst == 'EIT') {
            $t1 = 14 * 3600;
            $t2 = 24 * 3600;
            $t3 = 48 * 3600;
        }
        else if ($inst == 'HMI') {
            $t1 =  4 * 3600;
            $t2 =  8 * 3600;
            $t3 = 24 * 3600;
        }
        else if ($inst == 'LASCO') {
            $t1 =  8 * 3600;
            $t2 = 12 * 3600;
            $t3 = 24 * 3600;
        }
        else if ($inst == 'SECCHI') {
            $t1 =  84 * 3600;  // 3 days 12 hours
            $t2 = 120 * 3600;  // 5 days
            $t3 = 144 * 3600;  // 6 days
        }
        else if ($inst == 'SWAP') {
            $t1 =  4 * 3600;
            $t2 =  8 * 3600;
            $t3 = 12 * 3600;
        }

        // Return level
        if ($elapsed <= $t1) {
            return 1;
        }
        else if ($elapsed <= $t2) {
            return 2;
        }
        else if ($elapsed <= $t3) {
            return 3;
        }
        else if ($elapsed <= $t4){
            return 4;
        }
        else {
            return 5;
        }
    }

    /**
     * Parses input and returns a RegionOfInterest instance. Excepts input
     * in one of two formats:
     *
     *  1) x1, y1, x2, y2, OR
     *  2) x0, y0, width, height
     */
     private function _getRegionOfInterest($options, $params) 
     {

        include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

        $isset_x0 = isset($options['x0']);
        $isset_x1 = isset($options['x1']);
        $isset_x2 = isset($options['x2']);
        $isset_y0 = isset($options['y0']);
        $isset_y1 = isset($options['y1']);
        $isset_y2 = isset($options['y2']);
        $isset_height = isset($options['height']);
        $isset_width = isset($options['width']);


        // Region of interest: x1, x2, y1, y2
        if ($isset_x1 && $isset_y1 && $isset_x2 && $isset_y2) {

            $x1 = $options['x1'];
            $y1 = $options['y1'];
            $x2 = $options['x2'];
            $y2 = $options['y2'];

        } else if ( $isset_x0 && $isset_y0 && $isset_width && $isset_height ) {

            // Region of interest: x0, y0, width, height
            $x1 = $options['x0'] - 0.5 * $options['width'] * $params['imageScale'];
            $y1 = $options['y0'] - 0.5 * $options['height'] * $params['imageScale'];

            $x2 = $options['x0'] + 0.5 * $options['width'] * $params['imageScale'];
            $y2 = $options['y0'] + 0.5 * $options['height'] * $params['imageScale'];

        } else {

            throw new Exception(
                'Region of interest not specified: you must specify values ' .
                'for imageScale and either x1, x2, y1, and y2 or x0, y0, ' .
                'width and height.', 23
            );

        }

        // Create RegionOfInterest helper object
        return new Helper_RegionOfInterest($x1, $y1, $x2, $y2, $params['imageScale']);
    }


    /**
     * Creates the directory structure which will be used to cache
     * generated tiles.
     *
     * Note: mkdir may not set permissions properly due to an issue with umask.
     *       (See http://www.webmasterworld.com/forum88/13215.htm)

     *
     * @param string $filepath The filepath where the image is stored
     *
     * @return void
     */
    private function _createTileCacheDir($directory) {

        $cacheDir = HV_CACHE_DIR.'/tiles'.$directory;

        if ( !@file_exists($cacheDir) ) {
            @mkdir($cacheDir, 0775, true);
        }
    }

    /**
     * Builds a filename for a cached tile or image based on boundaries
     * and scale
     *
     * @param string $directory The directory containing the image
     * @param float  $filename  The filename of the image
     * @param float  $x         Tile X-coordinate
     * @param float  $y         Tile Y-coordinate
     *
     * @return string Filepath to use when locating or creating the tile
     */
    private function _getTileCacheFilename($directory, $filename, $scale, $x, $y, $difference='') {

        $baseDirectory = HV_CACHE_DIR.'/tiles';
        $baseFilename  = substr($filename, 0, -4);

        return sprintf(
            "%s%s/%s_%s_x%d_y%d%s.png", $baseDirectory, $directory,
            $baseFilename, $scale, $x, $y, $difference
        );
    }

    /**
     * Helper function to handle response code and response message with
     * output result as either JSON or JSONP
     *
     * @param int    $code HTTP response code to return
     * @param string $message  Message for the response code,
     * @param mixed  $data Data can be anything 
     *
     * @return void
     */
    private function _sendResponse(int $code, string $message, mixed $data) : void
    {
        http_response_code($code);
        $this->_printJSON(json_encode([
            'status_code' => $code,
            'status_txt' => $message,
            'data' => $data,
        ]));
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
    private function _printJSON($json, $xml=false, $utf=false) {

        // Wrap JSONP requests with callback
        if ( isset($this->_params['callback']) ) {

            // For XML responses, surround with quotes and remove newlines to
            // make a valid JavaScript string
            if ($xml) {
                $xmlStr = str_replace("\n", '', str_replace("'", "\'", $json));
                $json   = sprintf("%s('%s')", $this->_params['callback'],
                    $xmlStr);
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
     * Converts from tile coordinates to physical coordinates in arcseconds
     * and uses those coordinates to return an ROI object
     *
     * @return Helper_RegionOfInterest Tile ROI
     */
    private function _tileCoordinatesToROI ($x, $y, $scale, $jp2Scale,
        $tileSize, $offsetX, $offsetY) {

        $relativeTileSize = $tileSize * ($scale / $jp2Scale);

        // Convert tile coordinates to arcseconds
        $top    = $y * $relativeTileSize - $offsetY;
        $left   = $x * $relativeTileSize - $offsetX;
        $bottom = $top  + $relativeTileSize;
        $right  = $left + $relativeTileSize;

        // Scale coordinates
        $top    = $top * $jp2Scale;
        $left   = $left * $jp2Scale;
        $bottom = $bottom * $jp2Scale;
        $right  = $right  * $jp2Scale;

        // Regon of interest
        return new Helper_RegionOfInterest(
            $left, $top, $right, $bottom, $scale );
    }

    /**
     * Used to set a given date to the helioviewer minimum date set in
     * the configuration. If the date given is above the minimum date,
     * then the same date is returned. If the date given is before
     * the minimum date, then the date returned will be the minimum date.
     *
     * @param date The date to clamp to helioviewer's range
     *
     * @return date The date given, or the helioviewer minimum date.
     */
    private function _clampDate($date) {
        $minDate = new DateTime(HV_MINIMUM_DATE);
        if ($date < $minDate) {
            return $minDate;
        }
        return $date;
    }

    /**
     * Handles input validation
     *
     * @return bool Returns true if the input is valid with respect to the
     *              requested action.
     */
    public function validate() {

        switch( $this->_params['action'] ) {

        case 'saveWebClientState':
            $expected = array(
               'required' => array('json'),
            );
            break;

        case 'getWebClientState':
            $expected = array(
               'required' => array('state_id'),
            );
            break;

        case 'downloadScreenshot':
            $expected = array(
               'required' => array('id'),
               'optional' => array('force'),
               'ints'     => array('id'),
               'bools'    => array('force')
            );
            break;
        case 'getClosestImage':
            $expected = array(
               'required' => array('date', 'sourceId'),
               'dates'    => array('date'),
               'optional' => array('callback', 'switchSources'),
               'bools'      => array('switchSources'),
               'alphanum' => array('callback'),
               'ints'     => array('sourceId')
            );
            break;

        case 'getClosestImageDatesForSources':
            $expected = array(
               'required' => array('date', 'sources'),
               'dates'    => array('date'),
               'array_ints'  => array('sources'),
            );
            break;
        case 'getDataSources':
            $expected = array(
               'optional' => array('verbose', 'callback', 'enable'),
               'bools'    => array('verbose'),
               'alphanum' => array('callback')
            );
            break;
        case 'getTile':
            $expected = array(
                'required' => array('id', 'x', 'y', 'imageScale'),
                'dates'    => array('baseDiffTime'),
                'floats'   => array('imageScale'),
                'optional' => array('difference', 'diffCount', 'diffTime', 'baseDiffTime'),
                'ints'     => array('id', 'x', 'y', 'difference', 'diffCount', 'diffTime'),
                'choices'  => array('difference' => [0, 1, 2])
            );
            // baseDiffTime is required if difference == 2
            if (array_key_exists("difference", $this->_params) && $this->_params["difference"] == 2) {
                array_push($expected['required'], 'baseDiffTime');
            }
            break;
        case 'getJP2Header':
            $expected = array(
                'required' => array('id'),
                'ints'     => array('id'),
                'optional' => array('callback'),
                'alphanum' => array('callback')
            );
            break;
        case 'getNewsFeed':
            $expected = array(
                'optional' => array('callback'),
                'alphanum' => array('callback')
            );
            break;
        case 'getUsageStatistics':
            $expected = array(
                'optional' => array('resolution', 'callback','dateStart','dateEnd'),
                'dates' => array('dateStart','dateEnd'),
                'alphanum' => array('resolution', 'callback')
            );
            break;
        case 'getDataCoverage':
            $expected = array(
                'optional' => array('resolution','currentDate','startDate','endDate','callback','imageLayers','startDate','endDate','eventLayers'),
                'alphanum' => array('resolution', 'callback'),
                'dates'    => array()
            );
            break;
        case 'getDataCoverageTimeline':
            $expected = array(
                'optional' => array('resolution', 'endDate'),
                'alphanum' => array(),
                'dates'    => array('endDate')
            );
            break;
        case 'updateDataCoverage':
            $expected = array(
                'optional' => array('period', 'callback'),
                'alphanum' => array('period', 'callback')
            );
            break;
        case 'shortenURL':
            $expected = array(
                'required' => array('queryString'),
                'optional' => array('callback'),
                'encoded'  => array('queryString', 'callback')
            );
            break;
        case 'takeScreenshot':
            $expected = array(
                'required' => array('date', 'imageScale', 'layers'),
                'optional' => array('display', 'watermark', 'x1', 'x2',
                                    'y1', 'y2', 'x0', 'y0', 'width', 'height',
                                    'events', 'eventLabels', 'movieIcons', 'scale',
                                    'scaleType', 'scaleX', 'scaleY',
                                    'callback', 'switchSources'),
                'floats'   => array('imageScale', 'x1', 'x2', 'y1', 'y2',
                                    'x0', 'y0', 'scaleX', 'scaleY'),
                'ints'     => array('width', 'height'),
                'dates'    => array('date'),
                'bools'    => array('display', 'watermark', 'eventLabels',
                                    'scale', 'movieIcons', 'switchSources'),
                'alphanum' => array('scaleType', 'callback'),
                'layer'    => array('layers')
            );
            break;

        case 'postScreenshot':
            $expected = [
                'required' => ['json'],
            ];
            break;
        case 'getStatus':
            $expected = array(
                'optional' => array('key'),
                'alphanum' => array('key')
            );
            break;
        case "getSciDataScript":
            $expected = array(
                "required" => array('imageScale', 'sourceIds',
                                    'startDate', 'endDate',
                                    'lang'),
                "optional" => array('x0','y0', 'width', 'height',
                                    'x1','y1', 'x2','y2',
                                    'callback'),
                "floats"   => array('imageScale','x0','y0',
                                    'x1','y1','x2','y2'),
                "ints"     => array('width', 'height'),
                "dates"    => array('startDate', 'endDate'),
                "alphanum" => array('callback')
            );
            break;
        case 'downloadImage':
            $expected = array(
                'required' => array('id', 'scale'),
                'optional' => array('type'),
                'choices'  => array('type' => ['png', 'jpg', 'webp'])
            );
            break;
        case 'goto':
            $expected = array(
                'required' => array('id'),
                'alphanum' => array('id')
            );
            break;
        case 'getEclipseImage':
            $expected = array(
                'bools' => array('moon'),
                'optional' => array('moon')
            );
            break;
        default:
            break;
        }

        if ( isset($expected) ) {
            Validation_InputValidator::checkInput($expected, $this->_params,
                $this->_options);
        }

        return true;
    }
}
?>
