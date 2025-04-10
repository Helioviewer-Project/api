<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Movie_HelioviewerMovie Class Definition
 * Represents a static (e.g. mp4/webm) movie generated by Helioviewer
 *
 * Note: For movies, it is easiest to work with Unix timestamps since that
 *       is what is returned from the database. To get from a javascript
 *       Date object to a Unix timestamp, simply use
 *       "date.getTime() * 1000."
 *       (getTime returns the number of miliseconds)
 *
 * Movie Status:
 *  0   QUEUED
 *  1   PROCESSING
 *  2   COMPLETED
 *  3   ERROR
 *
 * 2011/05/24:
 *     http://flowplayer.org/plugins/streaming/pseudostreaming.html#prepare
 *
 * @category Movie
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Jaclyn Beck <jaclyn.r.beck@gmail.com>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR . '/../lib/alphaID/alphaID.php';
require_once HV_ROOT_DIR . '/../src/Database/ImgIndex.php';
require_once HV_ROOT_DIR . '/../src/Helper/DateTimeConversions.php';
require_once HV_ROOT_DIR . '/../src/Helper/HelioviewerLayers.php';
require_once HV_ROOT_DIR . '/../src/Helper/RegionOfInterest.php';
require_once HV_ROOT_DIR . '/../src/Helper/Serialize.php';

use Helioviewer\Api\Event\EventsStateManager;
use Helioviewer\Api\Sentry\Sentry;

/**
 * Exception to throw when performing an operation on a movie instance
 * that hasn't been processed into a movie.
 */
class MovieNotCompletedException extends Exception {}
class MovieLookupException extends Exception {}

class Movie_HelioviewerMovie {
    const STATUS_QUEUED = 0;
    const STATUS_PROCESSING = 1;
    const STATUS_COMPLETED = 2;
    const STATUS_ERROR = 3;

    const CACHE_DIR = 'api/HelioviewerMovie';
    public $id;
    public $frameRate;
    public $movieLength;
    public $maxFrames;
    public $numFrames;
    public $reqStartDate;
    public $reqEndDate;
    public $reqObservationDate;
    public $startDate;
    public $endDate;
    public $width;
    public $height;
    public $directory;
    public $filename;
    public $format;
    public $status;
    public $timestamp;
    public $modified;
    public $watermark;
    public $movieIcons;
    public $celestialBodies;
    public $followViewport;
    public $scale;
    public $scaleType;
    public $scaleX;
    public $scaleY;
    public $size;
    public $switchSources;
    public $publicId;
    public $imageScale;

    private $_db;
    private $_layers;
    private $_eventsManager;
    private $_roi;
    private $_timestamps = array();
    private $_frames     = array();

    private $_cachedir;
    private $_filename;
    private $_cached = false;

    /**
     * Prepares the parameters passed in from the api call and makes a
     * movie from them.
     *
     * @return {String} a url to the movie, or the movie will display.
     */
    public function __construct($publicId, $format='mp4') {
        $this->_db       = false;

        $this->_cachedir = Movie_HelioviewerMovie::CACHE_DIR;
        $this->_filename = urlencode($publicId.'.cache');

        $this->_cached = false;
        if ( HV_DISABLE_CACHE !== true ) {
            $cache = new Helper_Serialize($this->_cachedir, $this->_filename, 60);
            $info = $cache->readCache($verifyAge=true);
            // If cached movie is processing, don't load from cache so that
            // users get the fastest notification of the movie being done.
            // This also fixes an issue where the progress data breaks because the cached status
            // is processing, but the movie is done.
            if ( $info !== false && $info['status'] >= self::STATUS_COMPLETED ) {
                $this->_cached = true;
            }
        }

        if ( $this->_cached !== true ) {
            $info = $this->_loadFromDB($publicId, $format);
            $info['regionOfInterest'] = $info['roi'];
            if ( HV_DISABLE_CACHE !== true ) {
                if ( $cache->writeCache($info) ) {
                    $this->_cached = true;
                }
            }
        }

        $this->publicId     = $publicId;
        $this->format       = $format;
        $this->reqStartDate = $info['reqStartDate'];
        $this->reqEndDate   = $info['reqEndDate'];
        $this->reqObservationDate   = $info['reqObservationDate'];
        $this->startDate    = $info['startDate'];
        $this->endDate      = $info['endDate'];
        $this->timestamp    = $info['timestamp'];
        $this->modified     = (isset($info['modified']) ? $info['modified'] : null);
        $this->imageScale   = (float)$info['imageScale'];
        $this->frameRate    = (float)$info['frameRate'];
        $this->movieLength  = (float)$info['movieLength'];
        $this->id           = (int)alphaID($publicId,true,5,HV_MOVIE_ID_PASS);
        $this->status       = (int)$info['status'];
        $this->numFrames    = (int)$info['numFrames'];
        $this->width        = (int)$info['width'];
        $this->height       = (int)$info['height'];
        $this->watermark    = (bool)$info['watermark'];
        $this->movieIcons   = (bool)$info['movieIcons'];
        $this->celestialBodies = array(
            'labels'        => $info['celestialBodiesLabels'],
            'trajectories'  => $info['celestialBodiesTrajectories']
        );
        $this->followViewport   = (bool)$info['followViewport'];
        $this->scale        = (bool)$info['scale'];
        $this->scaleType    = $info['scaleType'];
        $this->scaleX       = (float)$info['scaleX'];
        $this->scaleY       = (float)$info['scaleY'];
        $this->maxFrames    = min((int)$info['maxFrames'],HV_MAX_MOVIE_FRAMES);
        $this->size       = (int)$info['size'];
        $this->switchSources = (isset($info['switchSources']) ? (bool)$info['switchSources'] : false);

        // Data Layers
        $this->_layers = new Helper_HelioviewerLayers($info['dataSourceString']);

        // ATTENTION! These two fields eventsLabels and eventSourceString needs to be kept in DB schema
        // We are keeping them to support old takeScreenshot , queueMovie requests

        // Events Manager
        $events_state_from_info = json_decode($info['eventsState'], true);

        if(!empty($events_state_from_info)) {
            $this->_eventsManager = EventsStateManager::buildFromEventsState($events_state_from_info);
        } else {
            $this->_eventsManager = EventsStateManager::buildFromLegacyEventStrings($info['eventSourceString'], (bool)$info['eventsLabels']);
        }

        // Regon of interest
        $this->_roi = Helper_RegionOfInterest::parsePolygonString($info['roi'], $info['imageScale']);
    }

    private function _dbSetup() {
        if ( $this->_db === false ) {
            $this->_db = new Database_ImgIndex();
        }
    }


    private function _loadFromDB($publicId, $format) {
        $this->_dbSetup();

        $id   = alphaID($publicId, true, 5, HV_MOVIE_ID_PASS);
        $info = $this->_db->getMovieInformation($id);

        if ( is_null($info) ) {
            throw new Exception('Unable to find the requested movie: '.$id,24);
        }

        return $info;
    }


    /**
     * Build the movie frames and movie
     */
    public function build() {
        $this->_dbSetup();

        date_default_timezone_set('UTC');

        if ( $this->status == 2 ) {
            return;
        }

        $this->_db->markMovieAsProcessing($this->id, 'mp4');

        try {
            $this->directory = $this->_buildDir();

            // If the movie frames have not been built create them
            if ( !@file_exists($this->directory.'frames') ) {
                require_once HV_ROOT_DIR .
                    '/../src/Image/Composite/HelioviewerMovieFrame.php';

                $t1 = date('Y-m-d H:i:s');

                // Set the actual start and end dates, frame-rate,
                // movie length, numFrames and dimensions
                $this->_setMovieProperties();

                Sentry::setContext('Queued Movie', [
                    'id' => $this->id,
                    'frameRate' => $this->frameRate,
                    'movieLength' => $this->movieLength,
                    'maxFrames' => $this->maxFrames,
                    'numFrames' => $this->numFrames,
                    'reqStartDate' => $this->reqStartDate,
                    'reqEndDate' => $this->reqEndDate,
                    'reqObservationDate' => $this->reqObservationDate,
                    'startDate' => $this->startDate,
                    'endDate' => $this->endDate,
                    'width' => $this->width,
                    'height' => $this->height,
                    'directory' => $this->directory,
                    'filename' => $this->filename,
                    'format' => $this->format,
                    'status' => $this->status,
                    'timestamp' => $this->timestamp,
                    'modified' => $this->modified,
                    'watermark' => $this->watermark,
                    'movieIcons' => $this->movieIcons,
                    'celestialBodies' => $this->celestialBodies,
                    'followViewport' => $this->followViewport,
                    'scale' => $this->scale,
                    'scaleType' => $this->scaleType,
                    'scaleX' => $this->scaleX,
                    'scaleY' => $this->scaleY,
                    'size' => $this->size,
                    'switchSources' => $this->switchSources,
                    'publicId' => $this->publicId,
                    'imageScale' => $this->imageScale,
                ]);
                // Build movie frames
                $this->_buildMovieFrames($this->watermark);

                $t2 = date('Y-m-d H:i:s');

                // Update status and log time to build frames
                $this->_db->finishedBuildingMovieFrames($this->id, $t1, $t2);
            }
            else {
                $this->filename = $this->_buildFilename();
            }
        }
        catch (Exception $e) {
            Sentry::capture($e);
            $this->_abort('Error encountered during movie frame compilation: ' . $e->getFile() . ":" . $e->getLine() . " - " . $e->getMessage() );
        }

        $t3 = time();

        // Compile movie
        try {
            $this->_encodeMovie();
        }
        catch (Exception $e) {
            Sentry::capture($e);
            $t4 = time();
            $this->_abort('Error encountered during video encoding. ' .
                'This may be caused by an FFmpeg configuration issue, ' .
                'or by insufficient permissions in the cache.', $t4 - $t3);
        }

        // Log buildMovie in statistics table
        if ( HV_ENABLE_STATISTICS_COLLECTION ) {
            include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';

            $statistics = new Database_Statistics();
            $statistics->log('buildMovie');
            $statistics->logRedis('buildMovie');
        }

        $this->_cleanUp();
    }

    /**
     * Returns information about the completed movie
     *
     * @throws
     * @return array A list of movie properties and a URL to the finished movie
     */
    public function getCompletedMovieInformation($verbose=false) {
        if (!$this->isComplete()) {
            throw new MovieNotCompletedException("Movie $this->publicId has not been completed.");
        }

        $info = array(
            'frameRate'  => $this->frameRate,
            'numFrames'  => $this->numFrames,
            'startDate'  => $this->startDate,
            'status'     => $this->status,
            'endDate'    => $this->endDate,
            'width'      => $this->width,
            'height'     => $this->height,
            'title'      => $this->getTitle(),
            'thumbnails' => $this->getPreviewImages(),
            'url'        => $this->getURL()
        );

        if ($verbose) {
            $extra = array(
                'timestamp'  => $this->timestamp,
                'duration'   => $this->_getDuration(),
                'imageScale' => $this->imageScale,
                'layers'     => $this->_layers->serialize(),
                'events'     => $this->_eventsManager->getState(),
                'x1'         => $this->_roi->left(),
                'y1'         => $this->_roi->top(),
                'x2'         => $this->_roi->right(),
                'y2'         => $this->_roi->bottom()
            );
            $info = array_merge($info, $extra);
        }

        return $info;
    }

    public function isComplete(): bool {
        return $this->status == Movie_HelioviewerMovie::STATUS_COMPLETED;
    }

    /**
     * Returns an array of filepaths to the movie's preview images
     */
    public function getPreviewImages() {
        $rootURL = str_replace(HV_CACHE_DIR, HV_CACHE_URL, $this->_buildDir());

        $images = array();

        foreach ( array('icon', 'small', 'medium', 'large', 'full') as $size) {
            $images[$size] = $rootURL.'preview-'.$size.'.png';
        }

        return $images;
    }

    /**
     * Returns the base filepath for movie without any file extension
     */
    public function getFilepath($highQuality=false) {
        return $this->_buildDir().$this->_buildFilename($highQuality);
    }

    private function _getDuration() {
        return $this->numFrames / $this->frameRate;
    }

    /**
     * Returns the duration of the movie in seconds
     * @throws MovieNotCreatedException if the movie has not been processed
     */
    public function getDuration() {
        if (!$this->isComplete()) {
            throw new MovieNotCompletedException("Duration for $this->publicId is unknown since the movie has not been processed yet.");
        }
        return $this->_getDuration();
    }

    public function getURL() {
        return str_replace(HV_CACHE_DIR, HV_CACHE_URL, $this->_buildDir()) . $this->_buildFilename();
    }

    public function getCurrentFrame() {
        $dir = dirname($this->getFilepath()) . '/frames/';
        $pattern = '/^frame([0-9]{1,4})\.bmp$/';
        $newest_timestamp = 0;
        $newest_file = '';
        $newest_frame = '';

        if ($handle = @opendir($dir)) {
            while ( ($fname = readdir($handle)) !== false )  {
                // Skip current and parent directories ('.', '..')
                if ( preg_match('/^\.{1,2}$/', $fname) ) {
                    continue;
                }

                // Skip non-matching file patterns
                if ( !preg_match($pattern, $fname, $matches) ) {
                    continue;
                }

                $timedat = @filemtime($dir.'/'.$fname);

                if ($timedat > $newest_timestamp) {
                    $newest_timestamp = $timedat;
                    $newest_file = $fname;
                    $newest_frame = $matches[1];
                }
            }
        }

        // Do not call closedir boolean if we can not open directory
        if (false === $handle) {
            throw new \Exception("Could not find requested movie frames");
        }

        @closedir($handle);

        return ($newest_frame>0) ? (int)$newest_frame : null;
    }

    /**
     * Cancels movie request
     *
     * @param string $msg Error message
     */
    protected function _abort($msg, $procTime=0) {
        $this->_dbSetup();
        $this->_db->markMovieAsInvalid($this->id, $procTime);
        $this->_cleanUp();

        throw new Exception('Unable to create movie '.$this->publicId.': '.$msg);
    }

    /**
     * Determines the directory to store the movie in.
     *
     * @return string Directory
     */
    private function _buildDir() {
        $date = str_replace('-', '/', substr($this->timestamp, 0, 10));

        return sprintf('%s/movies/%s/%s/', HV_CACHE_DIR, $date,
            $this->publicId);
    }

    private function _getStartDate() {
        if (is_null($this->startDate)) {
            $this->_prepDates();
        }
        return $this->startDate;
    }

    private function _getEndDate() {
        if (is_null($this->endDate)) {
            $this->_prepDates();
        }
        return $this->endDate;
    }

    /**
     * Determines filename to use for the movie
     *
     * @param string $extension Extension of the movie format to be created
     *
     * @return string Movie filename
     */
    private function _buildFilename($highQuality=false) {
        $start = str_replace(array(':', '-', ' '), '_', $this->_getStartDate());
        $end   = str_replace(array(':', '-', ' '), '_', $this->_getEndDate());

        $suffix = ($highQuality && $this->format == 'mp4') ? '-hq' : '';

        return sprintf("%s_%s_%s%s.%s", $start, $end,
            $this->_layers->toString(), $suffix, $this->format);
    }

    /**
     * Takes in meta and layer information and creates movie frames from them.
     *
     * TODO: Use middle frame instead last one...
     * TODO: Create standardized thumbnail sizes
     *       (e.g. thumbnail-med.png = 480x320, etc)
     *
     * @return $images an array of built movie frames
     */
    private function _buildMovieFrames($watermark) {

        $this->_dbSetup();

        $frameNum = 0;

        // Movie frame parameters
        $options = array(
            'database'  => $this->_db,
            'compress'  => false,
            'interlace' => false,
            'watermark' => $watermark,
            'movie'     => true,
            'size'      => $this->size,
            'followViewport' => $this->followViewport,
            'startDate' => $this->_getStartDate(),
            'reqStartDate' => $this->reqStartDate,
            'reqEndDate' => $this->reqEndDate,
            'reqObservationDate' => $this->reqObservationDate,
            'switchSources' => $this->switchSources
        );

        // Index of preview frame
        $previewIndex = floor($this->numFrames/2);

        // Add tolerance for single-frame failures
        $numFailures = 0;

        // Compile frames
        foreach ($this->_getTimeStamps() as $time) {

            $filepath =  sprintf('%sframes/frame%d.bmp', $this->directory, $frameNum);

            try {
                $screenshot = new Image_Composite_HelioviewerMovieFrame(
                    $filepath, $this->_layers, $this->_eventsManager,
                    $this->movieIcons, $this->celestialBodies,
                    $this->scale, $this->scaleType, $this->scaleX, $this->scaleY,
                    $time, $this->_roi, $options);

                if ( $frameNum == $previewIndex ) {
                    // Make a copy of frame to be used for preview images
                    $previewImage = $screenshot;
                }

                $frameNum++;
                array_push($this->_frames, $filepath);
            }
            catch (Exception $e) {
                Sentry::capture($e);
                $numFailures += 1;

                if ($numFailures <= 3) {
                    // Recover if failure occurs on a single frame
                    $this->numFrames--;
                }
                else {
                    error_log("Movie creation error: " . $e->getFile() . ": " . $e->getLine());
                    // Otherwise proprogate exception to be logged
                    throw $e;
                }
            }
        }

        $this->_createPreviewImages($previewImage);
    }

    /**
     * Remove movie frames and directory
     *
     * @return void
     */
    private function _cleanUp() {
        $dir = $this->directory.'frames/';

        // Clean up movie frame images that are no longer needed
        if ( @file_exists($dir) ) {
            foreach (glob("$dir*") as $image) {
                @unlink($image);
            }
            @rmdir($dir);
        }
    }

    /**
     * Creates preview images of several different sizes
     */
    private function _createPreviewImages(&$screenshot) {

        // Create preview image
        $preview = $screenshot->getIMagickImage();
        $preview->setImageCompression(IMagick::COMPRESSION_LZW);
        $preview->setImageCompressionQuality(PNG_LOW_COMPRESSION);
        $preview->setInterlaceScheme(IMagick::INTERLACE_PLANE);

        // Thumbnail sizes to create
        $sizes = array(
            'large'  => array(640, 480),
            'medium' => array(320, 240),
            'small'  => array(240, 180),
            'icon'   => array( 64,  64)
        );

        foreach ($sizes as $name=>$dimensions) {
            $thumb = clone $preview;
            $thumb->thumbnailImage($dimensions[0], $dimensions[1], true);

            // Add black border to reach desired preview image sizes
            $borderWidth  = ceil(($dimensions[0]-$thumb->getImageWidth()) /2);
            $borderHeight = ceil(($dimensions[1]-$thumb->getImageHeight())/2);

            $thumb->borderImage('black', $borderWidth, $borderHeight);
            $thumb->cropImage($dimensions[0], $dimensions[1], 0, 0);

            $thumb->writeImage($this->directory.'preview-'.$name.'.png');
            $thumb->destroy();
        }

        $preview->writeImage($this->directory.'preview-full.png');
        $preview->destroy();
    }

    private function markFinished(string $format, float $time_to_build) {
        $this->_db->markMovieAsFinished($this->id, $format, $time_to_build);
        $this->status = Movie_HelioviewerMovie::STATUS_COMPLETED;
    }

    /**
     * Builds the requested movie
     *
     * Makes a temporary directory to store frames in, calculates a timestamp
     * for every frame, gets the closest image to each timestamp for each
     * layer. Then takes all layers belonging to one timestamp and makes a
     * movie frame out of it. When done with all movie frames, phpvideotoolkit
     * is used to compile all the frames into a movie.
     *
     * @return void
     */
    private function _encodeMovie() {

        require_once HV_ROOT_DIR.'/../src/Movie/FFMPEGEncoder.php';

        // Compute movie meta-data
        $layerString = $this->_layers->toHumanReadableString();

        // Date string
        $dateString = $this->getDateString();

        // URLS
        $url1 = HV_WEB_ROOT_URL . '/?action=playMovie&id='
                                . $this->publicId.'&format='.$this->format
                                . '&hq=true';
        $url2 = HV_WEB_ROOT_URL . '/?action=downloadMovie&id='
                                . $this->publicId.'&format='.$this->format
                                . '&hq=true';

        // Title
        $title = sprintf('%s (%s)', $layerString, $dateString);

        // Description
        $description = sprintf(
            'The Sun as seen through %s from %s.',
            $layerString, str_replace('-', ' to ', $dateString)
        );

        // Comment
        $comment = sprintf(
            'This movie was produced by Helioviewer.org. See the original ' .
            'at %s or download a high-quality version from %s.', $url1, $url2
        );

        // MP4 filename
        $filename = str_replace('webm', 'mp4', $this->filename);

        // Limit frame-rate floating-point precision
        // https://bugs.launchpad.net/helioviewer.org/+bug/979231
        $frameRate = round($this->frameRate, 1);

        if($this->size == 1){
            $this->width = 1280;
            $this->height = 720;
        }else if($this->size == 2){
            $this->width = 1920;
            $this->height = 1080;
        }else if($this->size == 3){
            $this->width = 2560;
            $this->height = 1440;
        }else if($this->size == 4){
            $this->width = 3840;
            $this->height = 2160;
        }

        // Create and FFmpeg encoder instance
        $ffmpeg = new Movie_FFMPEGEncoder(
            $this->directory, $filename, $frameRate, $this->width,
            $this->height, $title, $description, $comment
        );

        $this->_dbSetup();


        // Create H.264 videos
        $t1 = time();
        $ffmpeg->setFormat('mp4');
        $ffmpeg->createHQVideo();
        $ffmpeg->createVideo(true);
        $ffmpeg->createFlashVideo();
        //$ffmpeg->createGifVideo();

        // Mark mp4 movie as completed
        $t2 = time();
        $this->markFinished('mp4', $t2 - $t1);


        // Create a low-quality webm movie for in-browser use if requested
        $t3 = time();
        $ffmpeg->setFormat('webm');
        $ffmpeg->createVideo();

        // Mark movie as completed
        $t4 = time();
        $this->markFinished('webm', $t4 - $t3);
    }

    /**
     * Returns a human-readable title for the video
     * @throws MovieNotCompletedException since the title relies on the layer dates from processing the movie.
     */
    public function getTitle() {
        if (!$this->isComplete()) {
            throw new MovieNotCompletedException("The title for $this->publicId is not available yet");
        }

        date_default_timezone_set('UTC');

        $layerString = $this->_layers->toHumanReadableString();
        $dateString  = $this->getDateString();

        return sprintf('%s (%s)', $layerString, $dateString);
    }

    /**
     * Returns a human-readable date string
     */
    public function getDateString() {
        date_default_timezone_set('UTC');

        if (substr($this->_getStartDate(), 0, 9) == substr($this->_getEndDate(), 0, 9)) {
            $endDate = substr($this->_getEndDate(), 11);
        }
        else {
            $endDate = $this->_getEndDate();
        }

        return sprintf('%s - %s UTC', $this->_getStartDate(), $endDate);
    }

    /**
     * Returns an array of the timestamps for the key movie layer
     *
     * For single layer movies, the number of frames will be either
     * HV_MAX_MOVIE_FRAMES, or the number of images available for the
     * requested time range. For multi-layer movies, the number of frames
     * included may be reduced to ensure that the total number of
     * SubFieldImages needed does not exceed HV_MAX_MOVIE_FRAMES
     */
    private function _getTimeStamps(): array {
        // If timestamps have already been processed, return them.
        if (!empty($this->_timestamps)) {
            return $this->_timestamps;
        }

        $this->_dbSetup();

        $layerCounts = array();

        // Determine the number of images that are available for the request
        // duration for each layer
        foreach ($this->_layers->toArray() as $layer) {
            $n = $this->_db->getDataCount($this->reqStartDate, $this->reqEndDate, $layer['sourceId'], $this->switchSources);
            if ($n === false) {
                throw new MovieLookupException("Failed to query data count for $this->publicId on source " . $layer['sourceId']);
            }

            $layerCounts[$layer['sourceId']] = $n;
        }

        // Choose the maximum number of frames that can be generated without
        // exceeded the server limits defined by HV_MAX_MOVIE_FRAMES
        $numFrames       = 0;
        $imagesRemaining = $this->maxFrames;
        $layersRemaining = $this->_layers->length();

        // Sort counts from smallest to largest
        asort($layerCounts);

        // Determine number of frames to create
        foreach($layerCounts as $dataSource => $count) {
            $numFrames = min($count, ($imagesRemaining / $layersRemaining));
            $imagesRemaining -= $numFrames;
            $layersRemaining -= 1;
        }

        // Number of frames to use
        $numFrames = floor($numFrames);

        // Get the entire range of available images between the movie start
        // and end time
        $entireRange = $this->_db->getDataRange($this->reqStartDate, $this->reqEndDate, $dataSource, $this->switchSources);

        // Sub-sample range so that only $numFrames timestamps are returned
        for ($i = 0; $i < $numFrames; $i++) {
            $index = round($i * (sizeOf($entireRange) / $numFrames));
            array_push($this->_timestamps, $entireRange[$index]['date']);
        }

        return $this->_timestamps;
    }

    /**
     * Determines dimensions to use for movie and stores them
     *
     * @return void
     */
    private function _setMovieDimensions() {
        $this->width  = round($this->_roi->getPixelWidth());
        $this->height = round($this->_roi->getPixelHeight());

        // Width and height must be divisible by 2 or ffmpeg will throw
        // an error.
        if ($this->width % 2 === 1) {
            $this->width += 1;
        }

        if ($this->height % 2 === 1) {
            $this->height += 1;
        }
    }

    private function _prepDates() {
        if ($this->status != 2) {
            // Store actual start and end dates that will be used for the movie
            $this->startDate = $this->_getTimeStamps()[0];
            $this->endDate   = $this->_getTimeStamps()[sizeOf($this->_getTimeStamps()) - 1];
        }
    }

    /**
     * Determines some of the movie details and saves them to the database
     * record
     */
    private function _setMovieProperties() {
        $this->_dbSetup();

        $this->_prepDates();

        $this->filename = $this->_buildFilename();

        $this->numFrames = sizeOf($this->_getTimeStamps());

        if ($this->numFrames == 0) {
            $this->_abort('No images available for the requested time range');
        }

        if ($this->frameRate) {
            $this->movieLength = $this->numFrames / $this->frameRate;
        }
        else {
            $this->frameRate = min(30, max(1, $this->numFrames / $this->movieLength) );
        }

        $this->_setMovieDimensions();

        if($this->size == 1){
            $width = 1280;
            $height = 720;
        }else if($this->size == 2){
            $width = 1920;
            $height = 1080;
        }else if($this->size == 3){
            $width = 2560;
            $height = 1440;
        }else if($this->size == 4){
            $width = 3840;
            $height = 2160;
        }else{
            $width = $this->width;
            $height = $this->height;
        }

        // Update movie entry in database with new details
        $this->_db->storeMovieProperties(
            $this->id, $this->_getStartDate(), $this->_getEndDate(), $this->numFrames,
            $this->frameRate, $this->movieLength, $width, $height
        );
    }

    /**
     * Adds black border to movie frames if neccessary to guarantee a 16:9
     * aspect ratio
     *
     * Checks the ratio of width to height and adjusts each dimension so that
     * the ratio is 16:9. The movie will be padded with a black background in
     * JP2Image.php using the new width and height.
     *
     * @return array Width and Height of padded movie frames
     */
    private function _setAspectRatios() {
        $width  = $this->_roi->getPixelWidth();
        $height = $this->_roi->getPixelHeight();

        $ratio = $width / $height;

        // Adjust height if necessary
        if ( $ratio > 16/9 ) {
            $adjust  = (9/16) * $width / $height;
            $height *= $adjust;
        }

        $dimensions = array('width'  => $width,
                            'height' => $height);

        return $dimensions;
    }

    /**
     *  Deletes a movie's cached information.
     */
    public static function DeleteFromCache($publicId) {
        $cachedir = Movie_HelioviewerMovie::CACHE_DIR;
        $filename = urlencode($publicId.'.cache');
        $cache = new Helper_Serialize($cachedir, $filename, 60);
        $cache->deleteFromCache();
    }

    /**
     * Returns HTML for a video player with the requested movie loaded
     */
    public function getMoviePlayerHTML() {

        $filepath = str_replace(HV_ROOT_DIR, '../', $this->getFilepath());
        $css      = "width: {$this->width}px; height: {$this->height}px;";
        $duration = $this->numFrames / $this->frameRate;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Helioviewer.org - <?php echo $this -> filename; ?></title>
    <script type="text/javascript" src="//html5.kaltura.org/js"></script>
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.4.2/jquery.js" type="text/javascript"></script>
</head>
<body>
<div style="text-align: center;">
    <div style="margin-left: auto; margin-right: auto; <?php echo $css; ?>";>
        <video style="margin-left: auto; margin-right: auto;" poster="<?php echo "$filepath.bmp"?>" durationHint="<?php echo $duration; ?>">
            <source src="<?php echo $filepath.'.mp4' ?>" />
            <source src="<?php echo $filepath.'.webm' ?>" />
            <source src="<?php echo $filepath.'.flv' ?>" />
        </video>
    </div>
</div>
</body>
</html>
<?php
    }
}
?>
