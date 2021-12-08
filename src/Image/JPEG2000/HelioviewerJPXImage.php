<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Helioviewer-specific JPEG 2000 JPX Image Class Definition
 * Class for generating JPX images in Helioviewer
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once 'JPXImage.php';

class Image_JPEG2000_HelioviewerJPXImage extends Image_JPEG2000_JPXImage {

    private $_sourceId;
    private $_startTime;
    private $_endTime;
    private $_cadence;
    private $_message;
    private $_summaryFile;
    private $_url;

    /**
     * Create a new Helioviewer JPX image
     *
     * @param int    $sourceId   Image source id
     * @param string $startTime  Requested start time for JPX
     *                           (ISO 8601 UTC date string)
     * @param string $endTime    Requested finish time for JPX
     *                           (ISO 8601 UTC date string)
     * @param int    $cadence    Number of seconds between each frame in the
     *                           image series
     * @param bool   $linked     Whether or not requested JPX image should be
     *                           a linked JPX
     * @param string $outputFile Filename to use for generated JPX file
     *
     * @return void
     */
    public function __construct($sourceId, $startTime, $endTime, $cadence, $linked, $filename, $middleFrames = false) {

        $this->_sourceId  = $sourceId;
        $this->_startTime = $startTime;
        $this->_endTime   = $endTime;
        $this->_cadence   = $cadence;

        $directory = HV_JP2_DIR.'/movies/';
        // Add suffix to file name for middle frames request to differentiate them
        // from normal requests for caching or parallel-requests purposes.
        $suffix         = $middleFrames ? 'mid' : '';
        $filepath       = $directory.$filename.$suffix;
        $this->_jpxFile = $filepath;

        $this->_url = HV_JP2_ROOT_URL.'/movies/'.$filename;

        // Location to store JPX generation summary information
        $this->_summaryFile = substr($filepath, 0, -3).'json';

        parent::__construct($filepath);

        // Query frames from the database for this request.
        list($images, $timestamps) = $this->_getFrames($middleFrames);
        $this->_timestamps = $timestamps;
        $this->_images     = $images;

        // Compare images requested from the database to the existing JPX
        // movie (if it exists) and determine if a new one should be generated.
        if ( $this->_shouldGenerateNewJpx($timestamps) ) {
            try {
                // Make sure that at least some movie frames were found
                if ( sizeOf($images) > 0 ) {
                    $this->buildJPXImage($images, $linked);
                }
                else {
	                //$this->_removeFileGenerationReport();
                    throw new Exception('No images were found for the ' . 'requested time range.', 12); // Do not log
                }
            }
            catch (Exception $e) {
                //$this->_removeFileGenerationReport();
                throw new Exception('Error encountered during JPX creation: ' . $e->getMessage(), 60);
            }
            $this->_writeFileGenerationReport();
        }
        else {
            // If the JPX exists, but no JSON is present,
            // kdu_merge is still running.
            if ( !@file_exists($this->_summaryFile) ) {
                $i = 0;

                // Wait five seconds and check to see if processing is
                // finished.  If not, sleep and try again.
                while ($i < 23) {
                    sleep(5);
                    if ( @file_exists($this->_summaryFile) ) {
                        return;
                    }
	                $i++;
                }

                // If the summary file is still not present after 120 seconds,
                // display an error message
                throw new Exception('JPX is taking an unusually long time to '.
                    'process. Please try again in 1-2 minutes.', 61);
            }
        }
    }

    /**
     * Retrieves the frames from the database to satisfy this request.
     * 
     * @param bool $middleFrames flag to determine whether or not to select
     *                           images by midpoint.
     */
    private function _getFrames($middleFrames) {
        if ($middleFrames == false) {
            return $this->_queryJPXImageFrames();
        } else {
            return $this->_queryJPXImageFramesMidPoint();
        }
    }

    /**
     * Determines if a JPX movie should be generated/regenerated.
     * 
     * Compares the provided frame timestamps against the frames in the existing
     * JPX file (if any). If there are new frames in the database, then the
     * JPX movie will be regenerated with all the new frames.
     */
    private function _shouldGenerateNewJpx($db_frame_timestamps) {
        // Check if the file already exists
        $jpx_exists = file_exists($this->_jpxFile);
        $summary_exists = file_exists($this->_summaryFile);

        // If the jpx file does not exist, a new one must be generated.
        if (!$jpx_exists) {
            return true;
        }

        // If the JPX exists, but no JSON is present
        if (!$summary_exists) {
            // then kdu_merge is still running.
            // no need to generate a new jpx.
            return false;
        }

        // If the jpx & summary files do exist
        $summary = $this->_loadSummary();
        // then compare the frames from the database to the frames in the
        // JPX summary.
        $diff = array_diff($db_frame_timestamps, $summary->frames);
        // If there are any differences in frames
        if (count($diff) > 0) {
            // then generate a new JPX
            return true;
        } else {
            // Otherwise, use the cached file
            return false;
        }
    }

    /**
     * Loads and returns the summary contents as a json object.
     */
    private function _loadSummary() {
        $summary_fp = fopen($this->_summaryFile, 'r');
        $summary_contents = fread($summary_fp, filesize($this->_summaryFile));
        fclose($summary_fp);

        return json_decode($summary_contents);
    }
	
	/**
     * Return list of JP2 files to use for JPX generation selected by Mid Points
     *
     * @return array List of filepaths of images to use during JPX generation
     *               as well as a list of the times for each image in the
     *               series.
     */
    private function _queryJPXImageFramesMidPoint() {

        include_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();
		
		// Parse List of dates and convert them to Unix Timestaps
		$startTimesArray                 = explode(',', $this->_startTime);
		$endTimesArray                   = explode(',', $this->_endTime);
		
		if(count($startTimesArray) < 1 || count($endTimesArray) < 1){
			throw new Exception('At least one Start and End date need to be specified. Please use timestamps separated with commas.', 61);
		}
		
		if(count($startTimesArray) != count($endTimesArray)){
			throw new Exception('Number of Start dates doesn\'t match the number of End dates. Please use equal amount of Start and End dates.', 61);
		}
		
		$images = array();
        $dates  = array();
		
		foreach($startTimesArray as $k => $start){
			$end = $endTimesArray[$k];
			$middle = round(($start + $end) / 2);
			
			$results = $imgIndex->getDataMidPoint($start, $end, $middle, $this->_sourceId);
			if($results && isset($results['id'])){
				$filepath = HV_JP2_DIR.$results['filepath'].'/'.$results['filename'];
				array_push($images, $filepath);
				array_push($dates, strtotime($results['date']));
			}else{
				array_push($images, null);
				array_push($dates, null);
			}
		}

        return array($images, $dates);
    }
	
    /**
     * Return list of JP2 files to use for JPX generation
     *
     * @return array List of filepaths of images to use during JPX generation
     *               as well as a list of the times for each image in the
     *               series.
     */
    private function _queryJPXImageFrames() {

        include_once HV_ROOT_DIR.'/../src/Helper/DateTimeConversions.php';
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();

        // Replace start and end request dates with actual matches in order to
        // account for gaps at either side
        $this->_checkRequestDates($imgIndex);
        $start = toUnixTimestamp($this->_startTime);
        $end   = toUnixTimestamp($this->_endTime);


        // 'cadence' parameter was omitted
        //  + If the number of frames available is less than HV_MAX_JPX_FRAMES,
        //    return them all.
        //  + If the number of frames exceeds HV_MAX_JPX_FRAMES, return the
        //    maximum number of frames allowed, spaced evenly between the
        //    requested start and end dates.  Output a warning message
        //    indicating that the cadence was changed.
        if ( $this->_cadence === false ) {
            $count = $imgIndex->getDataCount($this->_startTime,
                $this->_endTime, $this->_sourceId);

            if ( $count > HV_MAX_JPX_FRAMES ) {
                $this->_cadence = floor(($end - $start) / HV_MAX_JPX_FRAMES);
                $numFrames      = HV_MAX_JPX_FRAMES;
                $this->_message = 'Movie cadence has been changed ' .
                  /*'from one image every ' . $oldCadence  . ' second(s) ' .*/
                    'to one image every ' . $this->_cadence . ' seconds ' .
                    'between your requested start and end dates ' .
                    'in order to avoid exceeding the maximum of ' .
                    HV_MAX_JPX_FRAMES . ' frames allowed per request.';
            }
            else {
                return $this->_queryJPXImageFramesByRange($imgIndex);
            }
        }
        // 'cadence' parameter set to -1 by JHelioviewer, indicating that
        // the user has selected the 'get all' option.
        //  + If the number of frames available is less than HV_MAX_JPX_FRAMES,
        //    return them all.
        //  + If the number of frames exceeds HV_MAX_JPX_FRAMES, return every
        //    available frame until HV_MAX_JPX_FRAMES is reached.  JPX is
        //    thus truncated prior to reaching the requested end date. Output
        //    a warning message indicating the truncation date.
        else if ( $this->_cadence <= 0 ) {
            $count = $imgIndex->getDataCount($this->_startTime,
                $this->_endTime, $this->_sourceId);

            if ( $count > HV_MAX_JPX_FRAMES ) {
                // Force the method below to stop at the limiti of
                // HV_MAX_JPX_FRAMES and send output a warning message
                // indicating the effective end date of the movie due to
                // truncation.
                return $this->_queryJPXImageFramesByRange($imgIndex,
                    HV_MAX_JPX_FRAMES);
            }
            else {
                // Safe to return all images since the total is below
                // HV_MAX_JPX_FRAMES
                return $this->_queryJPXImageFramesByRange($imgIndex);
            }
        }
        // Cadence was manually specified check to make sure it is reasonable
        else {
            $numFrames = ceil(($end - $start) / $this->_cadence);

            if ( $numFrames > HV_MAX_JPX_FRAMES ) {

                $oldCadence = $this->_cadence;
                $this->_cadence = floor(($end - $start) / HV_MAX_JPX_FRAMES);
                $numFrames      = HV_MAX_JPX_FRAMES;

                $this->_message = 'Movie cadence has been changed ' .
                  /*'from one image every ' . $oldCadence  . ' second(s) ' .*/
                    'to one image every ' . $this->_cadence . ' seconds ' .
                    'between your requested start and end dates ' .
                    'in order to avoid exceeding the maximum of ' .
                    HV_MAX_JPX_FRAMES . ' frames allowed per request.';
            }
        }

        return $this->_queryJPXImageFramesByCadence($imgIndex, $numFrames);
    }

    /**
     * Retrieve filepaths and timestamps of all images of a given type between
     * the start and end dates specified.  Optionally limit the result set and
     * create a warning message indicating that the JPX was truncated.
     *
     * @param object $imgIndex  An ImgIndex object with access to the database
     * @param int    $maxFrames Optionally limit the size of the result set
     *
     * @return array List of filepaths to images to use for JPX generation
     *               as well as a list of times for each image in the series.
     */
    private function _queryJPXImageFramesByRange($imgIndex, $maxFrames=null) {

        $images = array();
        $dates  = array();

        $results = $imgIndex->getDataRange($this->_startTime,
            $this->_endTime, $this->_sourceId, $maxFrames);

        foreach ($results as $img) {
            $filepath = HV_JP2_DIR.$img['filepath'].'/'.$img['filename'];

            array_push($images, $filepath);
            array_push($dates, toUnixTimestamp($img['date']));
        }

        if ( $maxFrames != null && count($results) == $maxFrames ) {
            $dateLastFrame = $results[(count($results)-1)]['date'];

            $this->_message = 'The movie was truncated upon reaching the ' .
                $maxFrames . ' frame limit.  All available images from your ' .
                'requested start date through ' . $dateLastFrame . ' ' .
                'are included in the movie.';
        }

        return array($images, $dates);
    }

    /**
     * Retrieve filepaths and timestamps for images at a specified cadence of
     * a given type between the specified start and end dates.
     *
     * @param object $imgIndex  An ImgIndex object with access to the database
     * @param int    $numFrames The number of frames to go into the JPX movie
     *
     * @return array List of filepaths to images to use during JPX generation
     *               as well as a list of  times for each image in the series.
     */
    private function _queryJPXImageFramesByCadence($imgIndex, $numFrames) {
        $images = array();
        $dates  = array();

        // Timer
        $time = toUnixTimestamp($this->_endTime);

        // Get nearest JP2 images to each time-step
        for ($i = 0; $i < $numFrames; $i++) {
            // Get next image
            $isoDate = toISOString(parseUnixTimestamp($time));

            $img = $imgIndex->getDataFromDatabase($isoDate, $this->_sourceId);
            $filepath = HV_JP2_DIR.$img['filepath'].'/'.$img['filename'];

            // Ignore redundant images
            if ( !$images || $images[0] != $filepath ) {
                array_unshift($images, $filepath);
                array_unshift($dates, toUnixTimestamp($img['date']));
            }
            $time -= $this->_cadence;
        }

        // Add entry for start time if it isn't already included
        $img = $imgIndex->getDataFromDatabase($this->_startTime,
            $this->_sourceId);
        $jp2 = HV_JP2_DIR.$img['filepath'].'/'.$img['filename'];

        if ( $images && $images[0] != $jp2 ) {
            array_unshift($images, $jp2);
            array_unshift($dates, toUnixTimestamp($img['date']));
        }

        return array($images, $dates);
    }

    /**
     * Check the request start and end dates. If either are outside of the
     * range of available data, they are adjusted to fall within the available
     * data range. If the request range falls completely outside of the range
     * of available data, no movie is generated.
     *
     * @param object $imgIndex An instance of ImgIndex
     *
     * @return void
     */
    private function _checkRequestDates($imgIndex) {
        // TODO  08/02/2010:  Make note when dates use differ significantly
        //                    from request date.  Perhaps instead of returning
        //                    a "message" parameter, just return the items of
        //                    interest: startTime, endTime, overmax, etc.
        $startImage = $imgIndex->getClosestDataAfterDate($this->_startTime,
            $this->_sourceId);
        $endImage   = $imgIndex->getClosestDataBeforeDate($this->_endTime,
            $this->_sourceId);

        $this->_startTime = isoDateToMySQL($startImage['date']);
        $this->_endTime   = isoDateToMySQL($endImage['date']);
    }

    /**
     * Create a summary file for the generated JPX file including the
     * filepath, image timestamps, and any warning messages encountered during
     * the creation process.
     *
     * @return void
     */
    private function _writeFileGenerationReport() {
        $contents = array(
            'uri'     => $this->_url,
            'frames'  => $this->_timestamps,
            'message' => $this->_message
        );

        $fp = @fopen($this->_summaryFile, 'w');
        @fwrite($fp, json_encode($contents));
        @fclose($fp);
    }
    
    /**
     * Remove a summary file for the generated JPX file encountered during
     * the creation process.
     *
     * @return void
     */
    private function _removeFileGenerationReport() {
        if ( @file_exists($this->_summaryFile) ) {
            @unlink($this->_summaryFile);
        }
    }

    /**
     * Parse file containing summary information about a JPX file
     *
     * @return void
     */
    private function _parseFileGenerationReport() {

        if ( !@file_exists($this->_summaryFile) ) {
            throw new Exception('JPX Summary file does not exist.', 62);
        }

        $fp = @fopen($this->_summaryFile, 'r');
        $contents = @fread($fp, @filesize($this->_summaryFile));

        $summary = json_decode($contents);

        $this->_timestamps = $summary->frames;
        $this->_message    = $summary->message;
    }

    /**
     * Return the number of images that make up the JPX movie
     *
     * @return int Number of images
     */
    public function getNumJPXFrames() {
        return sizeOf($this->_images);
    }

    /**
     * Return a message describing any errors encountered during the JPX
     * generation process
     *
     * @return string Error message
     */
    public function getErrorMessage() {
        return $this->_message;
    }

    /**
     * Convert a regular HTTP URL to a JPIP URL
     *
     * @param string $jp2Dir      The JPEG 2000 archive root directory
     * @param string $jpipBaseURL The JPIP Server base URL
     *
     * @return string A JPIP URL.
     */
    public function getJPIPURL($jp2Dir=HV_JP2_DIR,
        $jpipBaseURL=HV_JPIP_ROOT_URL) {

        $webRootRegex = '/'.preg_replace("/\//", "\/", $jp2Dir).'/';
        $jpip = preg_replace($webRootRegex, $jpipBaseURL, $this->outputFile);

        return $jpip;
    }

    /**
     * Print summary information including HTTP/JPIP URI as JSON
     *
     * @param bool $jpip    Formats URI as JPIP URL if true
     * @param bool $verbose Includes any warning messages encountered during
     *                      file generation if true
     *
     * @return void
     */
    public function printJSON($jpip, $verbose) {

        // Read in jpx meta-information from cache
        if ( !isset($this->_timestamps) ) {
            $this->_parseFileGenerationReport();
        }

        $output = array('message' => $this->_message);

        // JPIP URL
        if ($jpip) {
            $output['uri'] = $this->getJPIPURL();
        }
        else {
            $output['uri'] = $this->_url;
        }

        // Image timestamps
        if ($verbose) {
            $output['frames'] = $this->_timestamps;
        }

        // Print
        header('Content-Type: application/json');
        print json_encode($output);
    }
}
?>