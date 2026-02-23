<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_Composite_HelioviewerCompositeImage class definition
 *
 * TODO: Instead of writing intermediate layers as files, store as
 * IMagick objects.
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Jaclyn Beck <jaclyn.r.beck@gmail.com>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2Image.php';
require_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
require_once HV_ROOT_DIR.'/../src/Module/SolarBodies.php';

use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Event\EventsApi;
use Helioviewer\Api\Event\EventsApiException;

class Image_Composite_HelioviewerCompositeImage {

    private   $_composite;
    private   $_dir;
    private   $_imageLayers;
    private   $_filepath;
    private   $_filename;
    private   $_timeOffsetX = 0;
    private   $_timeOffsetY = 0;
    private   $_format;
    protected $compress;
    protected $date;
    protected $db;
    protected $height;
    protected $interlace;
    protected $layers;
    protected $eventsManager;
    protected $movieIcons;
    protected $scale;
    protected $scaleType;
    protected $scaleX;
    protected $scaleY;
    protected $maxPixelScale;
    protected $roi;
    protected $imageScale;
    protected bool $grayscale;
    protected int $eclipse;
    protected bool $showMoon;
    protected $watermark;
    protected $width;
    protected $movie;
    protected $size;
    protected $followViewport;
    protected $startDate;
    protected $reqStartDate;
    protected $reqEndDate;
    protected $reqObservationDate;
    protected $switchSources;
    protected $celestialBodiesLabels;
    protected $celestialBodiesTrajectories;

    /**
     * Creates a new HelioviewerCompositeImage instance
     *
     * @param object $layers  A Helper_HelioviewerLayers object representing
     *                        the requested image layers
     * @param string $obsDate The date for which the composite image should be
     *                        created
     * @param object $roi     The rectangular region of interest defining the
     *                        composite image's boundaries
     * @param array  $options A list of optional parameters to use when
     *                        creating the composite image
     *
     * @return void
     */
    public function __construct($layers, $eventsManager, $movieIcons, $celestialBodies, $scale, $scaleType, $scaleX, $scaleY, $obsDate, $roi, $options) {

        set_time_limit(90); // Extend time limit to avoid timeouts

        // Default image settings (optimized for small filesize)
        $defaults = array(
            'database'  => false,
            'watermark' => true,
            'compress'  => true,
            'interlace' => true,
            'movie' 	=> false,
            'size' 	    => 0,
            'followViewport' => 0,
            'startDate' => false,
            'reqStartDate' => false,
            'reqEndDate' => false,
            'reqObservationDate' => false,
            'switchSources' => false,
            'grayscale' => false,
            'eclipse' => false,
            'moon' => false
        );

        $options = array_replace($defaults, $options);

        $this->width  = $roi->getPixelWidth();
        $this->height = $roi->getPixelHeight();
        $this->imageScale = $roi->imageScale();

        $this->db = $options['database'] ? $options['database'] : new Database_ImgIndex();
        $this->layers = $layers;
        $this->eventsManager = $eventsManager;
        $this->movieIcons = $movieIcons;
        $this->scale  = $scale;
        $this->scaleType = $scaleType;
        $this->scaleX = $scaleX;
        $this->scaleY = $scaleY;
        $this->date   = $obsDate;
        $this->roi    = $roi;

        $this->compress  = $options['compress'];
        $this->interlace = $options['interlace'];
        $this->watermark = $options['watermark'];
        $this->movie     = $options['movie'];
        $this->size      = $options['size'];
        $this->followViewport = $options['followViewport'];
        $this->startDate = $options['startDate'];
        $this->reqStartDate = $options['reqStartDate'];
        $this->reqEndDate = $options['reqEndDate'];
        $this->reqObservationDate = $options['reqObservationDate'];
        $this->switchSources = $options['switchSources'];
        $this->grayscale = $options['grayscale'];
        $this->eclipse = $options['eclipse'];
        $this->showMoon = $options['moon'];

        $this->celestialBodiesLabels = $celestialBodies['labels'];
        $this->celestialBodiesTrajectories = $celestialBodies['trajectories'];

        $this->maxPixelScale = 0.60511022;  // arcseconds per pixel
    }

    /**
     * Builds the composite image.
     *
     * TODO: Instead of writing out individual layers as files and then reading
     *       them back in simply use the IMagick
     *       objects directly.
     *
     * @return void
     */
    private function _buildCompositeImageLayers() {
        $imageLayers = array();

        // Find the closest image for each layer, add the layer information
        // string to it
        foreach ( $this->layers->toArray() as $layer ) {
	        if($this->switchSources){
				if($layer['sourceId'] == 13 && strtotime($this->date) < strtotime('2010-06-02 00:05:39')){
					$layer['sourceId'] = 3;
					$source = $this->db->getDatasourceInformationFromSourceId(3);
					$layer['name'] = $source['name'];
					$layer['uiLabels'] = $source['uiLabels'];
				}else if($layer['sourceId'] == 10 && strtotime($this->date) < strtotime('2010-06-02 00:05:36')){
					$layer['sourceId'] = 0;
					$source = $this->db->getDatasourceInformationFromSourceId(0);
					$layer['name'] = $source['name'];
					$layer['uiLabels'] = $source['uiLabels'];
				}else if($layer['sourceId'] == 11 && strtotime($this->date) < strtotime('2010-06-02 00:05:31')){
					$layer['sourceId'] = 1;
					$source = $this->db->getDatasourceInformationFromSourceId(1);
					$layer['name'] = $source['name'];
					$layer['uiLabels'] = $source['uiLabels'];
				}else if($layer['sourceId'] == 18 && strtotime($this->date) < strtotime('2010-12-06 06:53:41')){
					$layer['sourceId'] = 7;
					$source = $this->db->getDatasourceInformationFromSourceId(7);
					$layer['name'] = $source['name'];
					$layer['uiLabels'] = $source['uiLabels'];
				}else if($layer['sourceId'] == 19 && strtotime($this->date) < strtotime('2010-12-06 06:53:41')){
					$layer['sourceId'] = 6;
					$source = $this->db->getDatasourceInformationFromSourceId(6);
					$layer['name'] = $source['name'];
					$layer['uiLabels'] = $source['uiLabels'];
				}
			}

            $image = $this->_buildImageLayer($layer);
            array_push($imageLayers, $image);
        }

        // Check to see if layers were created
        if ( empty($imageLayers) ) {
            throw new Exception(
                'Unable to create layers needed for composite image', 30);
        }

        return $imageLayers;
    }

    /**
     * Builds a single layer image
     *
     * @param array $layer Associative array containing the layer properties
     *
     * @return object A HelioviewerImage instance (e.g. AIAImage or LASCOImage)
     */
    private function _buildImageLayer($layer) 
	{
        $image = $this->db->getClosestData($this->date, $layer['sourceId']);

        // Instantiate a JP2Image
        $jp2Filepath = HV_JP2_DIR.$image['filepath'].'/'.$image['filename'];

        $jp2 = new Image_JPEG2000_JP2Image($jp2Filepath, $image['width'], $image['height'], $image['scale']);

        $offsetX =   $image['refPixelX'] - ($image['width']  / 2);
        $offsetY = -($image['refPixelY'] - ($image['height'] / 2));

        $originalOffsetX = $offsetX;
        $originalOffsetY = $offsetY;



		if($this->followViewport){

			$timeOffset = $this->_calculateSunOffset($this->startDate, $this->date);

            if($this->reqObservationDate && $this->check_in_range($this->reqStartDate, $this->reqEndDate, $this->reqObservationDate)){
				$timeOffsetStart = $this->_calculateSunOffset($this->reqStartDate, $this->reqObservationDate);

				$timeOffset['hv_hpc_x_rot_delta_notscaled'] =  -$timeOffsetStart['hv_hpc_x_rot_delta_notscaled'] + $timeOffset['hv_hpc_x_rot_delta_notscaled'];
				$timeOffset['hv_hpc_y_rot_delta_notscaled'] =  -$timeOffsetStart['hv_hpc_y_rot_delta_notscaled'] + $timeOffset['hv_hpc_y_rot_delta_notscaled'];
			}

            $x = $timeOffset['hv_hpc_x_rot_delta_notscaled'] * $timeOffset['au_scalar'];
            $y = $timeOffset['hv_hpc_y_rot_delta_notscaled'] * $timeOffset['au_scalar'];

            $this->_timeOffsetX = $x / $this->roi->imageScale();
			$this->_timeOffsetY = $y / $this->roi->imageScale();

			$multi = $this->roi->imageScale() / $jp2->getScale();

			$offsetX = $offsetX + ($x*$multi) / $this->roi->imageScale();
			$offsetY = $offsetY + ($y*$multi) / $this->roi->imageScale();
		}
        // Options for individual layers
        $options = array(
            'date'          => $image['date'],
            'layeringOrder' => $layer['layeringOrder'],
            'opacity'       => $layer['opacity'],
            'compress'      => false,
            'movie'         => $this->movie,
            'size'          => $this->size,
            'originalOffsetX' => $originalOffsetX,
            'originalOffsetY' => $originalOffsetY,
            'followViewport'  => $this->followViewport,
            'grayscale'       => $this->grayscale
        );

        // For layers with transparent regions use PNG
        $ext = $layer['layeringOrder'] > 1 ? 'png' : 'bmp';

        // Choose a temporary filename (should never be used)
        $tmpFile = $this->_dir . '/' . rand() . '.' . $ext;

        // Choose type of image to create
        require_once HV_ROOT_DIR.'/../src/Image/Factory.php';
        $classname = Image_Factory::getImageClass($layer);

		//Difference JP2 File
		if(isset($layer['difference']) && $layer['difference'] > 0){
			if($layer['difference'] == 1){
				$date = new DateTime($image['date']);

				if($layer['diffTime'] == 6){ $dateDiff = 'year'; }
				else if($layer['diffTime'] == 5){ $dateDiff = 'month'; }
				else if($layer['diffTime'] == 4){ $dateDiff = 'week'; }
				else if($layer['diffTime'] == 3){ $dateDiff = 'day'; }
				else if($layer['diffTime'] == 2){ $dateDiff = 'hour'; }
				else if($layer['diffTime'] == 0){ $dateDiff = 'second'; }
				else{ $dateDiff = 'minute'; }

				$date->modify('-'.$layer['diffCount'].' '.$dateDiff);
				$dateStr = $date->format("Y-m-d\TH:i:s.000\Z");

				$jp2DifferenceLabel = ' [RD '.$layer['diffCount'].' '.$dateDiff.($layer['diffCount'] > 1 ? 's' : '').']';
			}elseif($layer['difference'] == 2){
				$dateStr = $layer['baseDiffTime'];

				$date = new DateTime($dateStr);
				$dateLabelStr = $date->format("Y-m-d H:i:s");
				$jp2DifferenceLabel = ' [BD '.$dateLabelStr.']';
			}

			//Create difference JP2 image
			$imageDifference = $this->db->getClosestDataBeforeDate($dateStr, $image['sourceId']);
	        $fileDifference   = HV_JP2_DIR.$imageDifference['filepath'].'/'.$imageDifference['filename'];
	        $jp2Difference = new Image_JPEG2000_JP2Image($fileDifference, $image['width'], $image['height'], $image['scale']);

			$options['jp2DiffPath']   =  $this->_dir . '/' . rand() . '.' . $ext;
	        $options['jp2Difference'] = $jp2Difference;
	        $options['jp2DifferenceLabel'] = $jp2DifferenceLabel;
		}

        return new $classname(
            $jp2, $tmpFile, $this->roi, $layer['uiLabels'],
            $offsetX, $offsetY, $options, $image['sunCenterOffsetParams'], $image['name'] );
    }

	private function check_in_range($start_date, $end_date, $date_from_user){
		// Convert to timestamp
		$start_ts = strtotime($start_date);
		$end_ts = strtotime($end_date);
		$user_ts = strtotime($date_from_user);

		// Check that user date is between start & end
		return (($user_ts >= $start_ts) && ($user_ts <= $end_ts));
	}

    private function _calculateSunOffset($sDate, $cDate) {
	    include_once HV_ROOT_DIR.'/../scripts/rot_hpc.php';

	    $startDateArr = explode(' ',$sDate);
	    $imageDateArr = explode(' ',$cDate);

	    $startTime = $startDateArr[0].'T'.$startDateArr[1].'.000Z';
		$imageTime = $imageDateArr[0].'T'.$imageDateArr[1].'.000Z';

		$imageTime = $startDateArr[0].'T'.$startDateArr[1].'.000Z';
		$startTime = $imageDateArr[0].'T'.$imageDateArr[1].'.000Z';

		$x = (($this->roi->left() + $this->roi->right()) / 2);
		$y = (($this->roi->top() + $this->roi->bottom()) / 2);

		// Scalar for normalizing HEK hpc_x and hpc_y coordinates based on the
		// apparent size of the Sun as seen from Earth at the specified
		// timestamp.
		// A reasonable approximation in the absence of the appropriate
		// spacecraft's position at the timestamp of the image(s) used for F/E
		// detection.
		$au_scalar = sunearth_distance($startTime);

		// Calculate radial distance for determining whether or not to
		// apply differential rotation.
		$hv_hpc_r_scaled = sqrt( pow($x,2) + pow($y,2) ) * $au_scalar;

		//if ( $hv_hpc_r_scaled <= 961.07064 ) {

		    // Differential rotation of the event marker's X,Y position
		    $rotateFromTime = $imageTime;
		    $rotateToTime   = $startTime;

		    list( $hv_hpc_x_notscaled_rot, $hv_hpc_y_notscaled_rot) =
		        rot_hpc( $x, $y,
		                 $rotateFromTime, $rotateToTime,
		                 $spacecraft=null, $vstart=null, $vend=null);

		    $hv_hpc_x_rot_delta_notscaled = $hv_hpc_x_notscaled_rot - $x;// !
		    $hv_hpc_y_rot_delta_notscaled = $hv_hpc_y_notscaled_rot - $y;// !

		    $hv_hpc_x_scaled_rot = $hv_hpc_x_notscaled_rot * $au_scalar;
		    $hv_hpc_y_scaled_rot = $hv_hpc_y_notscaled_rot * $au_scalar;

		    // These values will be used to place the event marker
		    // in the viewport, screenshots, and movies.
		    $hv_hpc_x_final = $hv_hpc_x_scaled_rot;
		    $hv_hpc_y_final = $hv_hpc_y_scaled_rot;
		//} else {
		    // Don't apply differential rotation to objects beyond
		    // the disk but do normalize them with the $au_scalar.
		//    $hv_hpc_x_final = $x * $au_scalar;
		//    $hv_hpc_y_final = $y * $au_scalar;

		//    $hv_hpc_x_rot_delta_notscaled = 0;// !
		//    $hv_hpc_y_rot_delta_notscaled = 0;// !
		//}

		return array(
			'originalX' => $x,
			'originalY' => $y,
			'x' => ($hv_hpc_x_final - $x),
			'y' => ($hv_hpc_y_final - $y),
			'tx' => $hv_hpc_x_final,
			'ty' => $hv_hpc_y_final,
			'au_scalar' => $au_scalar,
			'startTime' => $startTime,
			'imageTime' => $imageTime,
			'hv_hpc_r_scaled' => $hv_hpc_r_scaled,
			'hv_hpc_x_rot_delta_notscaled' => $hv_hpc_x_rot_delta_notscaled,
			'hv_hpc_y_rot_delta_notscaled' => $hv_hpc_y_rot_delta_notscaled
		);
    }

    /**
     * Builds each image separately and then composites them together if
     * necessary.
     *
     * @return string Composite image filepath
     */
    private function _buildCompositeImage() {

        // Composite images on top of one another if there are multiple layers.
        if ( sizeOf($this->_imageLayers) > 1 ) {

            $image = null;

            foreach ($this->_imageLayers as $layer) {
                $previous = $image;
                $image = $layer->getIMagickImage();

                // If $previous exists, then the images need to be composited.
                // For memory purposes, destroy $previous when done with it.
                if ($previous) {
                    $image->compositeImage(
                        $previous, IMagick::COMPOSITE_DSTOVER, 0, 0 );
                    $previous->destroy();
                }
            }
        }
        else {
            // For single layer images the composite image is simply the first
            // image layer
            $image = $this->_imageLayers[0]->getIMagickImage();
		}

        if ( $this->eventsManager->hasEvents() && $this->date != '2999-01-01T00:00:00.000Z') {
            $this->_addEventLayer($image);
        }

        if ( $this->movieIcons) {

            $this->_addMovieIcons($image);
        }

        if ( $this->celestialBodiesTrajectories != ''){
            $this->_addCelestialBodiesTrajectories($image);
        }

        if ( $this->celestialBodiesLabels != ''){
            $this->_addCelestialBodiesLabels($image);
        }

        if ( $this->scale && $this->scaleType == 'earth' ) {
            $this->_addEarthScale($image);
        }

        if ( $this->scale && $this->scaleType == 'scalebar' ) {
            $this->_addScaleBar($image);
        }

        if ( $this->watermark ) {
            $this->_addWatermark($image);
        }

        if ( $this->eclipse ) {
            $this->_addEclipseOverlay($image, $this->imageScale, $this->showMoon);
        }

        $this->_finalizeImage($image, $this->_filepath);

        // Store the IMagick composite image
        $this->_composite = $image;
    }

    /**
     * Finalizes image and writes it to a file
     *
     * @param Object $imagickImage An Imagick object
     * @param String $output       The filepath where the image will be
     *                             written to
     *
     * @return void
     */
    private function _finalizeImage($imagickImage, $output) {

        //set_time_limit(60); // Need to up the time limit that imagick is
                              // allowed to use to execute commands.

        // Compress image
        $this->_compressImage($imagickImage);

        // Add comment
        $comment = sprintf(
            'Image created by http://helioviewer.org using ' .
            'data from %s near %s',
            $this->layers->toHumanReadableString(),
            $this->date
        );
        $imagickImage->commentImage($comment);

        // Flatten image and write to disk
        //$imagickImage->setImageAlphaChannel(IMagick::ALPHACHANNEL_OPAQUE);
        $imagickImage->setImageBackgroundColor(new ImagickPixel('black'));
        $imagickImage->setImageAlphaChannel(11);
		$imagickImage->mergeImageLayers(imagick::LAYERMETHOD_FLATTEN);
        $imagickImage->setImageType(imagick::IMGTYPE_TRUECOLOR);
        //$imagickImage = $imagickImage->flattenImages();
        if($this->movie){
	        if($this->size == 1){
		        $imagickImage->thumbnailImage(1280, 720, true, true);
	        }else if($this->size == 2){
		        $imagickImage->thumbnailImage(1920, 1080, true, true);
	        }else if($this->size == 3){
		        $imagickImage->thumbnailImage(2560, 1440, true, true);
	        }else if($this->size == 4){
		        $imagickImage->thumbnailImage(3840, 2160, true, true);
	        }
        }
        $imagickImage->writeImage($output);
    }

    /**
     * Sets compression and interlacing settings for the composite image
     *
     * @param object $imagickImage An initialized Imagick object
     *
     * @return void
     */
    private function _compressImage($imagickImage) {

        // Apply compression based on image type for those formats that
        // support it
        if ( $this->_format === 'png' ) {

            // Set filetype
            $imagickImage->setImageFormat('PNG');

            // Compression type
            $imagickImage->setImageCompression(IMagick::COMPRESSION_LZW);

            // Compression quality
            $quality = $this->compress ? PNG_HIGH_COMPRESSION : PNG_LOW_COMPRESSION;
            $imagickImage->setImageCompressionQuality($quality);

            // Interlacing
            if ($this->interlace) {
                $imagickImage->setInterlaceScheme(IMagick::INTERLACE_PLANE);
            }

            // Quantization
            if ($this->compress) {
                // Reduce the number of colors used for the image
                // (256/layer + 512 for watermark)

                //Commented because right now this function doesnot make any changes
                //$imagickImage->quantizeImage(
                //    $this->layers->length() * 256 + 512,
                //    IMagick::COLORSPACE_RGB, 0, FALSE, FALSE );
            }

            $imagickImage->stripImage();
        }
        else if ( $this->_format === 'jpg' ) {

            // Set filetype
            $imagickImage->setImageFormat('JPG');

            // Compression type
            $imagickImage->setImageCompression(IMagick::COMPRESSION_JPEG);

            // Compression quality
            $quality = $this->compress ? JPG_HIGH_COMPRESSION : JPG_LOW_COMPRESSION;
            $imagickImage->setImageCompressionQuality($quality);

            // Interlacing
            if ( $this->interlace ) {
                $imagickImage->setInterlaceScheme(IMagick::INTERLACE_LINE);
            }
        }

        $imagickImage->setImageDepth(8);
    }

    /**
     * Composites visible Feature/Event markers, regions, and labels onto
     * image.
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addEventLayer($imagickImage) {

        if ( $this->width < 200 || $this->height < 200 ) {
            return;
        }

        $markerPinPixelOffsetX = 12;
        $markerPinPixelOffsetY = 38;

        require_once HV_ROOT_DIR.'/../src/Event/HEKAdapter.php';
        require_once HV_ROOT_DIR . "/../src/Helper/EventInterface.php";

        // Collect events from all data sources.
        // Collect all HEK events
        $hek = new Event_HEKAdapter();
        $event_categories = $hek->getNormalizedEvents($this->date, Array());

        $events_api_sources = ["CCMC", "RHESSI"];

        $observationTime = new DateTimeImmutable($this->date);
        $startDate = $observationTime->sub(new DateInterval("PT12H"));
        $length = new DateInterval("P1D");

        // Collect CCMC events if any
        try {

            $eventsApi = new EventsApi();
            $event_categories = array_merge($event_categories, $eventsApi->getEventsForSourceLegacy($observationTime, "CCMC"));
            // if there is no error only left is RHESSI to collect
            $events_api_sources = ["RHESSI"];

        } catch (EventsApiException $e) {
            Sentry::capture($e);
        }

        // Collect RHESSI events
        $event_categories = array_merge($event_categories, Helper_EventInterface::GetEvents($startDate, $length, $observationTime, $events_api_sources));

        // Lay down all relevant event REGIONS first
        $events_to_render = [];
        $events_manager = $this->eventsManager;
        $add_label_visibility_and_concept = function($events_data, $event_cat_pin, $event_group_name) use ($events_manager) {
            return array_map(function($ed) use ($events_manager, $event_cat_pin, $event_group_name) {
                $ed['concept'] = $event_group_name;
                $ed['label_visibility'] = $events_manager->isEventTypeLabelVisible($event_cat_pin) ? true : false;
                return $ed;
            }, $events_data);
        };


        foreach($event_categories as $event_cat) {
            
            $event_cat_pin = $event_cat['pin'];

            // if we dont  have any configuration for this event_type
            if (!$this->eventsManager->hasEventsForEventType($event_cat_pin)) {
                continue;
            }

            // Are we going to go for all children of this event type
            if ($this->eventsManager->appliesAllEventsForEventType($event_cat_pin)) {

                foreach($event_cat['groups'] as $ecg) {
                    $events_to_render = array_merge(
                        $events_to_render,  
                        $add_label_visibility_and_concept($ecg['data'], $event_cat_pin, $ecg['name'])
                    );
                }

                continue;
                
            }

            // Check each group  now 
            foreach($event_cat['groups'] as $event_cat_group) {
                
                // Applies for event type
                if($this->eventsManager->appliesFrmForEventType($event_cat_pin, $event_cat_group['name'])) {
                    
                    // applies all events for this group
                    if($this->eventsManager->appliesAllEventInstancesForFrm($event_cat_pin, $event_cat_group['name'])) {
                        $events_to_render = array_merge(
                            $events_to_render,  
                            $add_label_visibility_and_concept($event_cat_group['data'], $event_cat_pin, $event_cat_group['name'])
                        );
                    } else {

                        // applies some events for this group
                        $events_filtered_for_event_instances = [];

                        foreach($event_cat_group['data'] as $ev) {

                            if ($this->eventsManager->appliesEventInstance($event_cat_pin, $event_cat_group['name'], $ev)) {
                                $events_filtered_for_event_instances[] = $ev;
                            }

                        }

                        $events_to_render = array_merge(
                            $events_to_render,  
                            $add_label_visibility_and_concept($events_filtered_for_event_instances, $event_cat_pin, $event_cat_group['name'])
                        );

                    }
                }
            
            }
        }

        // Now handle the events
        foreach ($events_to_render as $event) {
            if ( array_key_exists('hv_poly_width_max_zoom_pixels', $event) ) {

                $width  = round($event['hv_poly_width_max_zoom_pixels']
                        * ($this->maxPixelScale/$this->roi->imageScale()));
                $height = round($event['hv_poly_height_max_zoom_pixels']
                        * ($this->maxPixelScale/$this->roi->imageScale()));

                if ( $width >= 1 && $height >= 1 ) {

                    $region_polygon = new IMagick(HV_ROOT_DIR.'/'.urldecode($event['hv_poly_url']) );

                    $x = (( $event['hv_poly_hpc_x_final']
                          - $this->roi->left()) / $this->roi->imageScale());
                    $y = (( $event['hv_poly_hpc_y_final']
                          - $this->roi->top() ) / $this->roi->imageScale());

                    $x = $x - $this->_timeOffsetX;
                    $y = $y - $this->_timeOffsetY;

                    $region_polygon->resizeImage(
                        $width, $height, Imagick::FILTER_LANCZOS,1);
                    $imagickImage->compositeImage(
                        $region_polygon, IMagick::COMPOSITE_DISSOLVE, $x, $y);
                }
            }
        }

        if ( isset($region_polygon) ) {
            $region_polygon->destroy();
        }

        // Now lay down the event MARKERS
        foreach( $events_to_render as $event ) {
            $marker = new IMagick(  HV_ROOT_DIR
                                  . '/resources/images/eventMarkers/'
                                  . $event['type'].'.png' );


            if ( array_key_exists('hpc_boundcc', $event) && ($event['hpc_boundcc'] != '')) {
		        $polygonCenterX = round($event['hv_poly_width_max_zoom_pixels'] * ($this->maxPixelScale/$this->roi->imageScale())) / 2;
	            $polygonCenterY = round($event['hv_poly_height_max_zoom_pixels'] * ($this->maxPixelScale/$this->roi->imageScale())) / 2;

		        $scaledMarkerX = round($event['hv_marker_offset_x'] * ($this->maxPixelScale/$this->roi->imageScale()));
	            $scaledMarkerY = round($event['hv_marker_offset_y'] * ($this->maxPixelScale/$this->roi->imageScale()));

				$polygonPosX = (( $event['hv_poly_hpc_x_final'] - $this->roi->left()) / $this->roi->imageScale());
                $polygonPosY = (( $event['hv_poly_hpc_y_final'] - $this->roi->top() ) / $this->roi->imageScale());

		        $x = round($polygonPosX + $polygonCenterX + $scaledMarkerX);
		        $y = round($polygonPosY + $polygonCenterY + $scaledMarkerY);
	        }else{
		        $x = round(( $event['hv_hpc_x'] - $this->roi->left()) / $this->roi->imageScale());
				$y = round((-$event['hv_hpc_y'] - $this->roi->top() ) / $this->roi->imageScale());
	        }

			$x = $x - $this->_timeOffsetX;
			$y = $y - $this->_timeOffsetY;

            $imagickImage->compositeImage($marker, IMagick::COMPOSITE_DISSOLVE, $x - $markerPinPixelOffsetX, $y - $markerPinPixelOffsetY);
            if ($event['label_visibility']) {
                $x = $x + 11;
                $y = $y - 24;

                /*$x = (( $event['hv_hpc_x_final'] - $this->roi->left())
                     / $this->roi->imageScale()) + 11;
                $y = ((-$event['hv_hpc_y_final'] - $this->roi->top() )
                     / $this->roi->imageScale()) - 24;

				$x = $x - $this->_timeOffsetX;
				$y = $y - $this->_timeOffsetY;*/

                $count = 0;

                foreach( explode("\n", $event['label']) as $value ) {
					//Fix unicode
					$value = str_replace(
						array('u03b1', 'u03b2', 'u03b3', 'u00b1', 'u00b2', '&deg;'),
						array('α', 'β', 'γ', '±', '²', '°'),
						$value
					);

                    // Outline words in black
                    $text = new IMagickDraw();
                    $text->setTextEncoding('utf-8');
                    $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
                    $text->setFontSize(10);
                    $text->setStrokeColor('#000C');
                    $text->setStrokeAntialias(true);
                    $text->setStrokeWidth(3);
                    $text->setStrokeOpacity(0.3);
                    $imagickImage->annotateImage($text, $x, $y+($count*12), 0, $value );

                    // Write words in white over outline
                    $text = new IMagickDraw();
                    $text->setTextEncoding('utf-8');
                    $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
                    $text->setFontSize(10);
                    $text->setFillColor('#ffff');
                    $text->setTextAntialias(true);
                    $text->setStrokeWidth(0);
                    $imagickImage->annotateImage($text, $x, $y+($count*12), 0, $value );

                    $count++;
                }
                // Cleanup
                $text->destroy();
            }

        }
        if ( isset($marker) ) {
            $marker->destroy();
        }
    }

    /**
     * Composites Shared YouTube movies markers and labels onto
     * image.
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addMovieIcons($imagickImage) {
        if ( $this->width < 200 || $this->height < 200 ) {
            return;
        }

        if ( $this->movieIcons === false ) {
            return false;
        }

        $markerPinPixelOffsetX = 12;
        $markerPinPixelOffsetY = 38;

		include_once HV_ROOT_DIR.'/../src/Database/MovieDatabase.php';
		include_once HV_ROOT_DIR.'/../src/Helper/HelioviewerLayers.php';
		include_once HV_ROOT_DIR.'/../src/Helper/RegionOfInterest.php';

        $movies = new Database_MovieDatabase();

        // Get a list of recent videos
        $videos = array();

        foreach( $movies->getSharedVideosByTime(0, 0, $this->date) as $video) { //date = '2000/01/01T00:00:00.000Z'

            $layers = new Helper_HelioviewerLayers($video['dataSourceString']);
			$layersArray = $layers->toArray();
			$name = '';
			if(count($layersArray) > 0){
				foreach($layersArray as $layer){
					$name .= $layer['name'].', ';
				}
			}
			$name = substr($name, 0, -2);

			// Regon of interest
			$roiObject = Helper_RegionOfInterest::parsePolygonString($video['roi'], $video['imageScale']);

			//Prepare coordinates
			$top = round($roiObject->top() / $roiObject->imageScale());
			$left = round($roiObject->left() / $roiObject->imageScale());
			$bottom = round($roiObject->bottom() / $roiObject->imageScale());
			$right = round($roiObject->right() / $roiObject->imageScale());

			$xCenter = $this->width / 2;
			$yCenter = $this->height / 2;

			//Icon and Label location
			$xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
			$yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();

			$x = -$xCenterOffset + $xCenter + (($right + $left)/2) / $this->roi->imageScale() * (float)$video['imageScale'];
			$y = -$yCenterOffset + $yCenter + (($top + $bottom)/2) / $this->roi->imageScale() * (float)$video['imageScale'];

			$x = $x - $this->_timeOffsetX;
			$y = $y - $this->_timeOffsetY;

			$marker = new IMagick(  HV_ROOT_DIR.'/resources/images/eventMarkers/movie.png' );

			$imagickImage->compositeImage($marker, IMagick::COMPOSITE_DISSOLVE, $x - 10, $y);//$x - 12, $y - 38

			//Label
			// Outline words in black
            $text = new IMagickDraw();
            $text->setTextEncoding('utf-8');
            $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
            $text->setFontSize(10);
            $text->setStrokeColor('#000C');
            $text->setStrokeAntialias(true);
            $text->setStrokeWidth(3);
            $text->setStrokeOpacity(0.3);
            $imagickImage->annotateImage($text, $x + 12, $y + 14, 0, $name );

            // Write words in white over outline
            $text = new IMagickDraw();
            $text->setTextEncoding('utf-8');
            $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
            $text->setFontSize(10);
            $text->setFillColor('#ffff');
            $text->setTextAntialias(true);
            $text->setStrokeWidth(0);
            $imagickImage->annotateImage($text, $x + 12, $y + 14, 0, $name );

            // Cleanup
            $text->destroy();

	        if ( isset($marker) ) {
	            $marker->destroy();
	        }

        }
    }

    /**
     * Parses Celestial Bodies Selection Strings into an Object of Arrays
     * with Observer Keys and an Array of Bodies that are selected per Observer.
     *
     * @param string $selectionString
     *
     * @return Object $parsedSelection an Object of Observer Keys with Array of bodies values.
     */
    private function _parseCelestialBodiesSelections($selection) {
        $parsedSelection = array();
        $selectionArray = explode(',',$selection);
        foreach($selectionArray as $observerBodyString){
            $observerBodyArray = explode('-',$observerBodyString);
            $observer = $observerBodyArray[0];
            $body = $observerBodyArray[1];
            if(!isset($parsedSelection[$observer])){
                $parsedSelection[$observer]=array();
            }
            array_push($parsedSelection[$observer],$body);
        }
        return $parsedSelection;
    }

    /**
     * Returns whether or not the given x,y coordinate exists in the viewport.
     */
    private function _inViewport($x, $y) {
        $x_in_view = 0 < $x && $x < $this->width;
        $y_in_view = 0 < $y && $y < $this->height;
        return $x_in_view && $y_in_view;
    }

    /**
     * Composites Celestial Bodies labels
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */

    private function _addCelestialBodiesLabels($imagickImage) {
        // Converts received date into unix timestamp in miliseconds
        $unixTimeInteger = (int)(strtotime($this->date)) * 1000;
        // Loads an instance of the solar bodies module
        $params = array('time'=>$unixTimeInteger);
        $SolarBodiesModule = new Module_SolarBodies($params);
        // Retrieves the current glossary based on predefined available data
        $glossary = $SolarBodiesModule->getSolarBodiesGlossaryForScreenshot();
        $glossaryMods = $glossary['mods'];
        $glossaryModsKeys = array_keys($glossaryMods);
        // Parses and prepares front end selections
        $selectedObserverBodies = $this->_parseCelestialBodiesSelections($this->celestialBodiesLabels);
        // Searches for labels at request time
        $labels = $SolarBodiesModule->getSolarBodiesLabelsForScreenshot($unixTimeInteger,$selectedObserverBodies);
        // Create pixel and text draw objects
        $black = new IMagickPixel('#000');
        $white = new IMagickPixel('white');
        // Outline text object
        $underText = new IMagickDraw();
        $underText->setTextEncoding('utf-8');
        //$underText->setFont('Helvetica');
        $underText->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $underText->setFontSize(12);
        $underText->setStrokeColor($black);
        $underText->setStrokeAntialias(true);
        $underText->setStrokeWidth(4);
        $underText->setStrokeOpacity(0.6);
        // Text object
        $text = new IMagickDraw();
        $text->setTextEncoding('utf-8');
        //$text->setFont('Helvetica');
        $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(12);
        $text->setFillColor($white);
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);

        // Isolate the labels array
        $coordinates = $labels["labels"];
        // Parse out the observers
        $observers = array_keys($selectedObserverBodies);
        $backToFrontObservers = array_reverse($observers);
        foreach($backToFrontObservers as $observer){
            // Parse out the celestial bodies under the given observer
            $bodies = $selectedObserverBodies[$observer];
            $backToFrontBodies = array_reverse($bodies);
            foreach($backToFrontBodies as $body){
                // There is data
                if($coordinates[$observer][$body]!=NULL){
                    // Body matches selection on front-end
                    if(in_array($body,$selectedObserverBodies[$observer])){
                        // Prepare for coordinates
                        $xCenter = $this->width / 2;
                        $yCenter = $this->height / 2;

                        // Calculate offset
                        $xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
                        $yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();

                        $labelPositionHCC = $this->_convertHPCtoHCC($coordinates[$observer][$body],true);
                        $x = -$xCenterOffset + $xCenter + $labelPositionHCC['x'] - 2;
                        $y = -$yCenterOffset + $yCenter + ($labelPositionHCC['y'] + 12);

                        // Calculate position of label
                        // $x = -$xCenterOffset + $xCenter + ((int)($coordinates[$observer][$body]->{'x'}) / $this->roi->imageScale()) -2;
                        // $y = -$yCenterOffset + $yCenter + ((((int)($coordinates[$observer][$body]->{'y'}) / $this->roi->imageScale()) - 12 ) * -1);

                        $bodyDisplayText = $body;
                        if(in_array($body,$glossaryModsKeys)){
                            $bodyDisplayText = $glossaryMods[$body]['name'];
                            if($glossaryMods[$body]['arrow']){
                                $bodyDisplayText = '↖'.$bodyDisplayText;
                            }
                        }

                        if ($this->_inViewport($x, $y)) {
                            // Outline labels in black
                            $imagickImage->annotateImage($underText, $x-1, $y, 0, ucfirst($bodyDisplayText));

                            // Write labels in white over outline
                            $imagickImage->annotateImage($text, $x, $y, 0, ucfirst($bodyDisplayText));
                        }
                    }
                }
            }
        }
        // Cleanup
        $underText->destroy();
        $text->destroy();

        // Cleanup
        $black->destroy();
        $white->destroy();
    }

    /**
     * Composites Celestial Bodies Trajectories
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addCelestialBodiesTrajectories($imagickImage){
        // Converts received date into unix timestamp in miliseconds
        $unixTimeInteger = (int)(strtotime($this->date)) * 1000;
        // Loads an instance of the solar bodies module
        $params = array('time'=>$unixTimeInteger);
        $SolarBodiesModule = new Module_SolarBodies($params);
        // Searches for glossary at request time
        $glossary = $SolarBodiesModule->getSolarBodiesGlossaryForScreenshot();
        $glossaryMods = $glossary['mods'];
        $glossaryModsKeys = array_keys($glossaryMods);
        // Parses and prepares front end selections
        $selectedObserverBodies = $this->_parseCelestialBodiesSelections($this->celestialBodiesTrajectories);
        // Searches for trajectories at requested time
        $trajectories = $SolarBodiesModule->getSolarBodiesTrajectoriesForScreenshot($unixTimeInteger,$selectedObserverBodies);
        // Create pixel and draw objects
        $front = new IMagickPixel('#A0A0A0');
        $behind = new ImagickPixel('#808080');
        $drawPoints = new IMagickDraw();
        $drawPoints->setFillColor($behind);
        // Use solid lines for normal trajectories
        $drawTrajectoriesSolid = new IMagickDraw();
        $drawTrajectoriesSolid->setStrokeColor($behind);
        $drawTrajectoriesSolid->setFillColor( new ImagickPixel( 'transparent' ) );
        // Track whether solid or dashed should be used
        $use_dashed = false;
        // Use dashed lines for portions of the trajectory that are behind the sun
        // Note: a 2nd imagick draw is needed due to a bug in the pecl extension that doesn't allow you to go back to a solid line.
        //       See https://github.com/Imagick/imagick/issues/568
        $drawTrajectoriesDashed = new IMagickDraw();
        $drawTrajectoriesDashed->setStrokeColor($behind);
        $drawTrajectoriesDashed->setFillColor( new ImagickPixel( 'transparent' ) );
        $r = 2; //radius for circles
        // Isolate the labels array
        $coordinates = $trajectories["trajectories"];
        // Parse out the observers
        $observers = array_keys($selectedObserverBodies);
        $backToFrontObservers = array_reverse($observers);
        foreach($backToFrontObservers as $observer){
            // Parse out the celestial bodies under the given observer
            $bodies = $selectedObserverBodies[$observer];
            $backToFrontBodies = array_reverse($bodies);
            foreach($backToFrontBodies as $body){
                // There is data
                if($coordinates[$observer][$body]!=NULL){
                    // Body matches selection on the front-end
                    if(in_array($body,$selectedObserverBodies[$observer])){
                        $times = array_keys((array)$coordinates[$observer][$body]);
                        $trajectoryCoords = array();
                        $lastTime = 0;
                        //$setColor = true;
                        $temporalCadence = 86400000;
                        if(in_array($body,$glossaryModsKeys)){
                            $temporalCadence = (int)$glossaryMods[$body]['cadence'];
                        }
                        $lastPlaneState = $coordinates[$observer][$body]->{$times[0]}->{'behind_plane_of_sun'};
                        // Set the color based on planet position relative to plane of sun
                        if($lastPlaneState == "True"){
                            $drawPoints->setFillColor($behind);
                            $drawTrajectoriesDashed->setStrokeColor($behind);
                            $drawTrajectoriesDashed->setStrokeDashArray( [3,2] );//add dashes
                            $use_dashed = true;
                        }else if($lastPlaneState == "False"){
                            $drawPoints->setFillColor($front);
                            $drawTrajectoriesSolid->setStrokeColor($front);
                            $use_dashed = false;
                        }
                        //process all timestamps
                        foreach($times as $time){
                            // Ensure more than $temporalCadence elapsed as unix timestamp in milliseconds since last point created
                            if($time - $lastTime >= $temporalCadence){

                                // Prepare for coordinates
                                $xCenter = $this->width / 2;
                                $yCenter = $this->height / 2;

                                // Calculate offset
                                $xCenterOffset = ($this->roi->left() + $this->roi->right()) / 2 / $this->roi->imageScale();
                                $yCenterOffset = ($this->roi->top() + $this->roi->bottom()) / 2 / $this->roi->imageScale();

                                $labelPositionHCC = $this->_convertHPCtoHCC($coordinates[$observer][$body]->{$time},true);
                                $x = -$xCenterOffset + $xCenter + $labelPositionHCC['x'] - 2;
                                $y = -$yCenterOffset + $yCenter + ($labelPositionHCC['y']);

                                // Calculate position of point coordinate
                                // $x = -$xCenterOffset + $xCenter + ((int)($coordinates[$observer][$body]->{$time}->{'x'}) / $this->roi->imageScale()) -2;
                                // $y = -$yCenterOffset + $yCenter + ((((int)($coordinates[$observer][$body]->{$time}->{'y'}) / $this->roi->imageScale()) ) * -1);

                                if($coordinates[$observer][$body]->{$time}->{'behind_plane_of_sun'} != $lastPlaneState){
                                    // Assemble array of points for trajectory line
                                    array_push($trajectoryCoords, array(
                                        'x'=>$x,
                                        'y'=>$y,
                                    ));
                                    // Create the trajectory line
                                    if ($use_dashed) {
                                        $drawTrajectoriesDashed->polyline($trajectoryCoords);
                                    } else {
                                        $drawTrajectoriesSolid->polyline($trajectoryCoords);
                                    }
                                    // Clear trajectory line
                                    $trajectoryCoords = array();
                                    // Assemble array of points for trajectory line
                                    array_push($trajectoryCoords, array(
                                        'x'=>$x,
                                        'y'=>$y
                                    ));
                                    // Set the color based on planet position relative to plane of sun
                                    if($coordinates[$observer][$body]->{$time}->{'behind_plane_of_sun'} == "True"){
                                        $drawPoints->setFillColor($behind);
                                        $drawTrajectoriesDashed->setStrokeColor($behind);
                                        $drawTrajectoriesDashed->setStrokeDashArray( [3,2] );//add dashes
                                        $use_dashed = true;
                                    }else if($coordinates[$observer][$body]->{$time}->{'behind_plane_of_sun'} == "False"){
                                        $drawPoints->setFillColor($front);
                                        $drawTrajectoriesSolid->setStrokeColor($front);
                                        $use_dashed = false;
                                    }
                                }else{
                                    // Assemble array of points for trajectory line
                                    array_push($trajectoryCoords, array(
                                        'x'=>$x,
                                        'y'=>$y
                                    ));
                                }

                                // Create circles locations of trajectories
                                $drawPoints->circle($x,$y,$x,$y+$r);
                                $lastTime = $time;
                                $lastPlaneState = $coordinates[$observer][$body]->{$time}->{'behind_plane_of_sun'};
                            }
                        }
                        // Create the trajectory line
                        if ($use_dashed) {
                            $drawTrajectoriesDashed->polyline($trajectoryCoords);
                        } else {
                            $drawTrajectoriesSolid->polyline($trajectoryCoords);
                        }
                    }
                }
            }
        }
        // Composite trajectory lines to image
        $imagickImage->drawImage($drawTrajectoriesSolid);
        $imagickImage->drawImage($drawTrajectoriesDashed);
        // Composite circles to image
        $imagickImage->drawImage($drawPoints);

        // Cleanup
        $front->destroy();
        $behind->destroy();
        $drawPoints->destroy();
        $drawTrajectoriesSolid->destroy();
        $drawTrajectoriesDashed->destroy();
    }

    /**
     * Composites an Earth-scale indicator
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addEarthScale($imagickImage) {
        // Calculate earth scale in piexls
        $rsunInArcseconds = 959.705;
        $earthFractionOfSun = 1/109.1;
        $earthScaleInPixels = round( 2 * $earthFractionOfSun *
                                    ($rsunInArcseconds /
                                     $this->roi->imageScale() ) );

        // Convert x,y position of top left of EarthScale rectangle
        // from arcseconds to pixels
        $topLeftX = (( $this->scaleX - $this->roi->left()) / $this->roi->imageScale());
        $topLeftY = ((-$this->scaleY - $this->roi->top() ) / $this->roi->imageScale());

        // Calculate height of the box based on the earth scale
        $rect_width  = 95;
        $rect_height = $earthScaleInPixels + 22;
        if ($rect_height < 82) {
            $rect_height = 82;
        }

        // Draw black rectangle background for indicator and label
        $draw = new ImagickDraw();
        $draw->setFillColor('#00000066');
        $draw->setStrokeColor('#888888FF');
        $draw->rectangle( $topLeftX, $topLeftY, $topLeftX+$rect_width,
                          $topLeftY+$rect_height );
        $imagickImage->drawImage($draw);

        // Draw Earth to scale
        if ( $earthScaleInPixels >= 1 ) {
            $earth = new IMagick(HV_ROOT_DIR .'/resources/images/earth.png');
            $x = 1 + $topLeftX + $rect_width/2  - $earthScaleInPixels/2;
            $y = 8 + $topLeftY + $rect_height/2 - $earthScaleInPixels/2;
            $earth->resizeImage($earthScaleInPixels, $earthScaleInPixels,
                Imagick::FILTER_LANCZOS,1);
            $imagickImage->compositeImage($earth, IMagick::COMPOSITE_DISSOLVE,
                intval($x), intval($y));
        }

        // Draw grey rectangle background for text label
        $draw = new ImagickDraw();
        $draw->setFillColor('#333333FF');
        $draw->rectangle( $topLeftX+1, $topLeftY+1, $topLeftX+$rect_width-1,
                          $topLeftY+16 );
        $imagickImage->drawImage($draw);

        // Write 'Earth Scale' label in white
        $text = new IMagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(10);
        $text->setFillColor('#ffff');
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);
        $x = $topLeftX + 21;
        $y = $topLeftY + 13;
        $imagickImage->annotateImage($text, $x, $y, 0,'Earth Scale');

        // Cleanup
        if ( isset($draw) ) {
            $draw->destroy();
        }
        if ( isset($earth) ) {
            $earth->destroy();
        }
        if ( isset($text) ) {
            $text->destroy();
        }
    }


     /**
     * Composites an Lenght-scale indicator
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addScaleBar($imagickImage) {
        $rect_width  = 73;
        $rect_height = 56;

        // Calculate earth scale in piexls

		$earthInPixels = 2 * (6367.5 / 695500.0) * (959.705 / $this->roi->imageScale());

		$sizeInKM = round((50 * (2 * 6367.5)) / $earthInPixels);
		$sizeInKMRounded = round($sizeInKM/1000)*1000;

		$scaleBarSizeInKM = number_format($sizeInKMRounded, 0, '.', ',') . ' km';

        // Convert x,y position of top left of EarthScale rectangle
        // from arcseconds to pixels
        if ( $this->scaleX != 0 && $this->scaleY != 0 ) {
            $topLeftX = (( $this->scaleX - $this->roi->left())
                / $this->roi->imageScale());
            $topLeftY = ((-$this->scaleY - $this->roi->top() )
                / $this->roi->imageScale());
        }
        else {
            $topLeftX = -1;
            $topLeftY = $imagickImage->getImageHeight() - $rect_height;
        }

        // Draw black rectangle background for indicator and label
        $draw = new ImagickDraw();
        $draw->setFillColor('#00000066');
        $draw->setStrokeColor('#888888FF');
        $draw->rectangle( $topLeftX, $topLeftY, $topLeftX+$rect_width,
                          $topLeftY+$rect_height );
        $imagickImage->drawImage($draw);

        // Draw Scalebar to scale
        $scalebar = new IMagick(HV_ROOT_DIR .'/resources/images/scalebar.png');
        $x = 1 + $topLeftX + $rect_width/2 - 25;
        $y = 8 + $topLeftY + $rect_height/2 + 4;
        $imagickImage->compositeImage($scalebar, IMagick::COMPOSITE_DISSOLVE, $x, $y);


        // Draw grey rectangle background for text label
        $draw = new ImagickDraw();
        $draw->setFillColor('#333333FF');
        $draw->rectangle( $topLeftX+1, $topLeftY+1, $topLeftX+$rect_width-1,
                          $topLeftY+16 );
        $imagickImage->drawImage($draw);

        // Write 'Earth Scale' label in white
        $text = new IMagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(10);
        $text->setFillColor('#ffff');
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);
        $x = $topLeftX + 15;
        $y = $topLeftY + 13;
        $imagickImage->annotateImage($text, $x, $y, 0,'Bar Scale');

        // Write 'Scale in km' label in white
        $text2 = new IMagickDraw();
        $text2->setTextEncoding('utf-8');
        $text2->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $text2->setFontSize(8);
        $text2->setFillColor('#ffff');
        $text2->setTextAntialias(true);
        $text2->setStrokeWidth(0);
        $text2->setTextAlignment(IMagick::ALIGN_CENTER);
        $x = $topLeftX + $rect_width/2 + 2;
        $y = $topLeftY + 32;
        $imagickImage->annotateImage($text2, $x, $y, 0,$scaleBarSizeInKM);

        // Cleanup
        if ( isset($draw) ) {
            $draw->destroy();
        }
        if ( isset($scalebar) ) {
            $scalebar->destroy();
        }
        if ( isset($text) ) {
            $text->destroy();
        }
        if ( isset($text2) ) {
            $text2->destroy();
        }
    }



    /**
     * Composites a watermark (the date strings of the image) onto the lower
     * left corner and the HV logo in the lower right corner.
     *
     * Layer names are added together as one string, and date strings are
     * added as a separate string, to line them up nicely. An example string
     * would  be:
     *
     *      -annotate +20+0 'EIT 304\nLASCO C2\n'
     * and:
     *      -annotate +100+0 '2003-01-01 12:00\n2003-01-01 11:30\n'
     *
     * These two strings are then layered on top of each other and put in the
     * southwest corner of the image.
     *
     * @param object $imagickImage An Imagick object
     *
     * @return void
     */
    private function _addWatermark($imagickImage) {

        // paths of the different logos to choose from
        $hv_logo = sprintf('%s/resources/images/watermark_circle_small_black_border.png', HV_ROOT_DIR);
        $hv_with_url_logo = sprintf("%s/resources/images/watermark_small_black_border.png", HV_ROOT_DIR);

        if ( $this->width < 200 || $this->height < 200 ) {
            return;
        }

        // Default text + URL
        $watermark = new IMagick($hv_with_url_logo);

        // If the image is too small, use only the circle, not the url, and scale it so it fits the image.
        if ( $this->width < 600 ) {
            $watermark->readImage($hv_logo);
            $scale = $this->width / 600;
            $scaled_watermark_width = intval($watermark->getImageWidth() * $scale);
            $watermark->scaleImage($scaled_watermark_width, $scaled_watermark_width);
        }

        // For whatever reason, compositeImage() doesn't carry over gravity
        // settings so the offsets must be relative to the top left corner of
        // the image rather than the desired gravity.
        $x = $this->width  - $watermark->getImageWidth()  - 10;
        $y = $this->height - $watermark->getImageHeight() - 10;

        $imagickImage->compositeImage($watermark, IMagick::COMPOSITE_DISSOLVE, intval($x), intval($y) );

        // If the image is too small, text won't fit. Don't put a date string
        // on it.
        if ( $this->width > 285 ) {
            $this->_addTimestampWatermark($imagickImage);
        }

        // Cleanup
        $watermark->destroy();
    }

    /**
     * Builds an imagemagick command to composite watermark text onto the image
     *
     * @param object $imagickImage An initialized IMagick object
     *
     * @return void
     */
    private function _addTimestampWatermark($imagickImage) {
        $nameCmd = '';
        $timeCmd = '';
        $height  = $imagickImage->getImageHeight();

        $lowerPad = $height - 15;

        // Put the names on first, then put the times on as a separate layer
        // so the times are nicely aligned.
        foreach ($this->_imageLayers as $layer) {
            $lowerPad -= 10;
            $nameCmd  .= $layer->getWaterMarkName();
            $timeCmd  .= $layer->getWaterMarkDateString();
        }

        $leftPad = 85;
        if ( $this->scale  == false ||
             $this->scaleX != 0     ||
             $this->scaleY != 0 ) {

            $leftPad = 12;
        }

        $black = new IMagickPixel('#000C');
        $white = new IMagickPixel('white');

        // Outline words in black
        $underText = new IMagickDraw();
        $underText->setTextEncoding('utf-8');
        $underText->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $underText->setFontSize(12);
        $underText->setStrokeColor($black);
        $underText->setStrokeAntialias(true);
        $underText->setStrokeWidth(2);
        $imagickImage->annotateImage($underText, $leftPad, $lowerPad, 0, $nameCmd);

        // Query font metrics to find out area width of the satellite image sources text (ex: SDO AIA 404)
        // Then we use this width to calculate where to put timestamps outline in image
        // last parameter true means multiline text
        $underTextQueryMetrics = $imagickImage->queryFontMetrics($underText, $nameCmd, true);

        // 10 is the default padding between source outline and timestamp outline
        $underTextRightPad = $underTextQueryMetrics['textWidth'] ? ($underTextQueryMetrics['textWidth'] + 10) : 120; 

        // Place timestamp outline 
        $imagickImage->annotateImage($underText, $underTextRightPad + $leftPad, $lowerPad, 0, $timeCmd);

        // Write text in white over the outline
        $text = new IMagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(12);
        $text->setFillColor($white);
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);
        $imagickImage->annotateImage($text, $leftPad, $lowerPad, 0, $nameCmd);

        // Query font metrics to find out area width of the satellite image sources text (ex: SDO AIA 404)
        // Then we use this width to calculate where to put timestamps texts in image.
        // last parameter true means multiline text
        $textQueryMetrics = $imagickImage->queryFontMetrics($text , $nameCmd, true);

        // 10 is the default padding between source text and timestamp text
        $textRightPad = $textQueryMetrics['textWidth'] ? ($textQueryMetrics['textWidth'] + 10) : 120; 

        // Place timestamp text 
        $imagickImage->annotateImage($text, $textRightPad + $leftPad, $lowerPad, 0, $timeCmd);

        // Cleanup
        $black->destroy();
        $white->destroy();
        $underText->destroy();
        $text->destroy();
    }

    /**
     * Sorts the layers by their associated layering order
     *
     * Layering orders that are supported currently are 3 (C3 images),
     * 2 (C2 images), 1 (EIT/MDI images).
     * The array is sorted by increasing layeringOrder.
     *
     * @param array &$images Array of Composite image layers
     *
     * @return array Array containing the sorted image layers
     */
    private function _sortByLayeringOrder(&$images) {
        $sortedImages = array();

        // Array to hold any images with layering order 2 or 3.
        // These images must go in the sortedImages array last because of how
        // compositing works.
        $groups = array('2' => array(), '3' => array());

        // Push all layering order 1 images into the sortedImages array,
        // push layering order 2 and higher into separate array.
        foreach ($images as $image) {
            $order = $image->getLayeringOrder();

            if ($order > 1) {
                array_push($groups[$order], $image);
            }
            else {
                array_push($sortedImages, $image);
            }
        }

        // Push the group 2's and group 3's into the sortedImages array now.
        foreach ($groups as $group) {
            foreach ($group as $image) {
                array_push($sortedImages, $image);
            }
        }

        // return the sorted array in order of smallest layering order to
        // largest.
        return $sortedImages;
    }

    private function _addEclipseOverlay(IMagick $image, float $scale, bool $showMoon) {
        include_once HV_ROOT_DIR . "/../src/Image/EclipseOverlay.php";
        // Add extra eclipse content to the image
        Image_EclipseOverlay::Apply($image, $scale, $showMoon);
    }

    public function _convertHPCtoHCC($inputBody,$useTan){
        $distanceInMeters = $inputBody->{'distance_sun_to_observer_au'} * 149597000000;
        $metersPerArcsecond = 724910;  //695500000 / 959.705;
        $helioprojectiveCartesian = array(
            'x' => ( $inputBody->{'x'} / 3600 ) * ( pi()/180 ) ,
            'y' => ( $inputBody->{'y'} / 3600 ) * ( pi()/180 )
        );
        if(!$useTan){
            $heliocentricCartesianReprojection = array(
                'x' => $distanceInMeters*cos( $helioprojectiveCartesian['y'])*sin( $helioprojectiveCartesian.x ),
                'y' => $distanceInMeters*sin( $helioprojectiveCartesian['y'] )
            );
        }else{
            $heliocentricCartesianReprojection = array(
                'x' => $distanceInMeters*tan( $helioprojectiveCartesian['x'] ),
                'y' => $distanceInMeters*( tan( $helioprojectiveCartesian['y'] ) / cos( $helioprojectiveCartesian['x'] ) )
            );
        }
        // console.log('HCC',heliocentricCartesianReprojection.x,heliocentricCartesianReprojection.y);
        $correctedCoordinates = array(
            'x' => ( $heliocentricCartesianReprojection['x'] / $metersPerArcsecond ) / $this->roi->imageScale(),
            'y' => -( $heliocentricCartesianReprojection['y'] / $metersPerArcsecond ) / $this->roi->imageScale()
        );
        return $correctedCoordinates;
    }

    /**
     * Builds the screenshot and saves it to the path specified
     *
     * @param string filepath Filepath to save the screenshot to
     *
     * @return void
     */
    public function build($filepath) {
        $this->_filepath = $filepath;

        $path_parts = pathinfo($filepath);
        $this->_dir      = $path_parts['dirname'];
        $this->_filename = $path_parts['basename'];
        $this->_format   = $path_parts['extension'];

        if ( !@file_exists($this->_dir) ) {
            if ( !@mkdir($this->_dir, 0775, true) ) {
                throw new Exception(
                    'Unable to create directory: '. $this->_dir, 50);
            }
        }

        // Build individual layers
        $this->_imageLayers = $this->_buildCompositeImageLayers();

        // Composite layers and create the final image
        $this->_buildCompositeImage();

        // Check to see if composite image was successfully created
        if ( !@file_exists($this->_filepath) ) {
            throw new Exception('The requested image is either unavailable ' .
                'or does not exist.', 31);
        }
    }


    /**
     * Displays the composite image
     *
     * @return void
     */
    public function display() {
        $fileinfo = new finfo(FILEINFO_MIME);
        $mimetype = $fileinfo->file($this->_filepath);
        header("Content-Disposition: inline; filename=\"". $this->_filename ."\"");
        header('Content-type: ' . $mimetype);
        $this->_composite->setImageFormat('png32');
        echo $this->_composite;
    }

    /**
     * Returns the IMagick object associated with the composite image
     *
     * @return object IMagick object
     */
    public function getIMagickImage() {
        return $this->_composite;
    }

    /**
     * Destructor
     *
     * @return void
     */
    public function __destruct() {

        // Destroy IMagick object
        if ( isset($this->_composite) ) {
            $this->_composite->destroy();
        }
    }

}
?>
