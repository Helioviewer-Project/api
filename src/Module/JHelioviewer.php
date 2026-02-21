<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Helioviewer JHelioviewer Module Class Definition
 * Provides methods for assisting JHelioviewer such as JPEG 2000 archive
 * searching and JPX file generation
 *
 * @category Modules
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
use Helioviewer\Api\Module\AbstractModule;
use Helioviewer\Api\Module\Module as ModuleInterface;
use Helioviewer\Api\Sentry\Sentry;

class Module_JHelioviewer extends AbstractModule implements ModuleInterface {

    private $_params;
    private $_options;
    private $_sourceInfo;

    /**
     * Create a JHelioviewer module instance
     *
     * @param array &$params API Request parameters.
     *
     * @return void
     */
    public function __construct(&$params) {
        $this->_params = $params;
        $this->_options = array();
    }

    /**
     * Validate and execute the requested API action
     *
     * @return void
     */
    public function execute() {

        if ( $this->validate() ) {
            try {
                $this->{$this->_params['action']}();
            }
            catch (Exception $e) {
                Sentry::capture($e);
                handleError($e->getMessage(), $e->getCode());
            }
        }
    }

    /**
     * Find the best match for a single JPEG2000 image and either
     * output a link to the image, or display the image directly.
     *
     * @return void
     */
    public function getJP2Image() {

        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
        $imgIndex = new Database_ImgIndex();
        include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
	$statistics = new Database_Statistics();

	// Optional parameters
        $defaults = array(
            'jpip' => false,
            'json' => false
        );

        $options = array_replace($defaults, $this->_options);

        // If image id is set, use it
        if ( isset($this->_params['id']) ) {
            $filepath = HV_JP2_DIR
                      . $imgIndex->getDataFilePathFromId($this->_params['id']);
        }
        else {

            // Filepath to JP2 Image
            $filepath = HV_JP2_DIR.$imgIndex->getDataFilePath(
                $this->_params['date'], $this->_params['sourceId']);
        }

        // Output results
        if ( $options['jpip'] ) {
	    $statistics->log("getJP2Image-jpip");
	    $statistics->logRedis("getJP2Image-jpip");
            if ( $options['json'] ) {
                header('Content-type: application/json;charset=UTF-8');
                echo json_encode(
                    array('uri' => $this->_getJPIPURL($filepath)) );
            }
            else {
                echo $this->_getJPIPURL($filepath);
            }
        }
        else {
	    $statistics->log("getJP2Image-web");
            $statistics->logRedis("getJP2Image-web");
	    $this->_displayJP2($filepath);
        }
    }

    /**
     * Construct a JPX image series
     *
     * @return void
     */
    public function getJPX() {

        include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/HelioviewerJPXImage.php';
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();

        // Optional parameters
        $defaults = array(
            'jpip'    => false,
            'cadence' => false,
            'linked'  => false,
            'verbose' => false
        );
        $options = array_replace($defaults, $this->_options);

/*
        // Make sure cadence is valid
        if ($options['cadence'] && $options['cadence'] <= 0) {
            $options['cadence'] = 1;
//            throw new Exception('Invalid cadence specified. ' .
//                                'Cadence must be greater than 0.');
        }
*/

        // sourceId as well as hierarchy labels/names are required
        $this->_getSourceIdInfo($imgIndex);

        // Compute filename
        $filename = $this->_getJPXFilename($options['cadence'], $options['linked']
        );

        // Create JPX image instance
        try {
            $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                $this->_params['sourceId'], $this->_params['startTime'],
                $this->_params['endTime'], $options['cadence'],
                $options['linked'], $filename
            );

            //Build Statistic
            include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
            $statistics = new Database_Statistics();
            $startTime = strtotime($this->_params['startTime']);
			$endTime = strtotime($this->_params['endTime']);
            $statistics->logJPX(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), $this->_params['sourceId']);
        }
        catch (Exception $e) {
            // If a problem is encountered, return an error message as JSON
            header('Content-type: application/json;charset=UTF-8');
            echo json_encode(
                array(
                    'error'   => $e->getMessage(),
                    'uri'     => null
                )
            );
            return;
        }

        // Chose appropriate action based on request parameters
        if ( $options['verbose'] ) {
            $jpx->printJSON($options['jpip'], $options['verbose']);
        }
        else {
            if ( $options['jpip'] ) {
                echo $jpx->getJPIPURL();
            }
            else {
                $jpx->displayImage();
            }
        }
    }

    /**
     * Construct a getJPXClosestToMidPoint image series
     *
     * @return void
     */
    public function getJPXClosestToMidPoint() {

        include_once HV_ROOT_DIR.'/../src/Image/JPEG2000/HelioviewerJPXImage.php';
        include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';

        $imgIndex = new Database_ImgIndex();

        // Optional parameters
        $defaults = array(
            'jpip'    => false,
            'cadence' => false,
            'linked'  => false,
            'verbose' => false
        );
        $options = array_replace($defaults, $this->_options);

        // sourceId as well as hierarchy labels/names are required
        $this->_getSourceIdInfo($imgIndex);

        // Compute filename
        $filename = $this->_getJPXMidPointFilename($options['cadence'], $options['linked']);

        // Create JPX image instance
        try {
            $jpx = new Image_JPEG2000_HelioviewerJPXImage(
                $this->_params['sourceId'], $this->_params['startTimes'],
                $this->_params['endTimes'], $options['cadence'],
                $options['linked'], $filename, true
            );

            //Build Statistic
            include_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
            $statistics = new Database_Statistics();
            $startArray = explode(",", $this->_params['startTimes']);
			$endArray = explode(",", $this->_params['endTimes']);
			$startTime = $startArray[0];
			$endTime = array_pop($endArray);

            $statistics->logJPX(date('Y-m-d H:i:s', $startTime), date('Y-m-d H:i:s', $endTime), $this->_params['sourceId']);

        }
        catch (Exception $e) {
            // If a problem is encountered, return an error message as JSON
            header('Content-type: application/json;charset=UTF-8');
            echo json_encode(
                array(
                    'error'   => $e->getMessage(),
                    'uri'     => null
                )
            );
            return;
        }

        // Chose appropriate action based on request parameters
        if ( $options['verbose'] ) {
            $jpx->printJSON($options['jpip'], $options['verbose']);
        }
        else {
            if ( $options['jpip'] ) {
                echo $jpx->getJPIPURL();
            }
            else {
                $jpx->displayImage();
            }
        }
    }

    /**
     * Return info for a given sourceId
     *
     * @param int    $sourceId  Id of data source
     * @param object &$imgIndex Database accessor
     *
     * @return array An array containing the observatory, instrument, detector and measurement associated with the
     *               specified datasource id.
     */
    private function _getSourceIdInfo(&$imgIndex) {

        if ( !isset($this->_sourceInfo) ) {
            // Get an associative array of the datasource metadata
            $this->_sourceInfo = $imgIndex->getDatasourceInformationFromSourceId($this->_params['sourceId']);
        }

        return $this->_sourceInfo;
    }

    /**
     * Generate the filename to use for storing JPXimage.
     *
     * @param int    $cadence   Number of seconds between each frame in the
     *                          image series
     * @param bool   $linked    Whether or not requested JPX image should be
     *                          a linked JPX
     *
     * @return string Filename to use for generated JPX image
     */
    private function _getJPXFilename($cadence, $linked) {

        $from = str_replace(':', '.', $this->_params['startTime']);
        $to   = str_replace(':', '.', $this->_params['endTime']);

        $filename_arr = array();
        foreach ( $this->_sourceInfo['uiLabels'] as $hierarchy ) {
            $filename_arr[] = $hierarchy['name'];
        }
        $filename_arr[] = 'F'.$from;
        $filename_arr[] = 'T'.$to;

        $filename = implode('_', $filename_arr);

        // Indicate the cadence in the filename if one was specified
        if ( $cadence ) {
            $filename .= 'B'.$cadence;
        }

        // Append an "L" to the filename for "Linked" JPX files
        if ( $linked ) {
            $filename .= 'L';
        }

        return str_replace(' ', '-', $filename).'.jpx';
    }

    /**
     * Generate the filename to use for storing JPXimageFromMidPoint.
     *
     * @param int    $cadence   Number of seconds between each frame in the
     *                          image series
     * @param bool   $linked    Whether or not requested JPX image should be
     *                          a linked JPX
     *
     * @return string Filename to use for generated JPX image
     */
    private function _getJPXMidPointFilename($cadence, $linked) {
		$startTimesArray                 = explode(',', $this->_params['startTimes']);
		$endTimesArray                   = explode(',', $this->_params['endTimes']);
        $endArrayValues = array_values($endTimesArray);

        $from = str_replace(':', '.', date("Y-m-d\TH:i:s\Z", current($startTimesArray)) );
        $to   = str_replace(':', '.', date("Y-m-d\TH:i:s\Z", end($endArrayValues)) );

        $filename_arr = array();
        foreach ( $this->_sourceInfo['uiLabels'] as $hierarchy ) {
            $filename_arr[] = $hierarchy['name'];
        }
        $filename_arr[] = 'F'.$from;
        $filename_arr[] = 'T'.$to;

        $filename = implode('_', $filename_arr);

        // Indicate the cadence in the filename if one was specified
        if ( $cadence ) {
            $filename .= 'B'.$cadence;
        }

        // Append an "L" to the filename for "Linked" JPX files
        if ( $linked ) {
            $filename .= 'L';
        }

        $hash_of_midpoints = md5($this->_params['startTimes'].">>".$this->_params['endTimes']);

        $result_filename = str_replace(' ', '-', $filename);

        return sprintf('%s.CMP%s.jpx', $result_filename, $hash_of_midpoints);

    }

    /**
     * Output the specified JP2 image directly
     *
     * @param string $filepath The location of the image to be displayed.
     *
     * @return void
     */
    private function _displayJP2($filepath) {
	    header('Content-Type: '  .image_type_to_mime_type(IMAGETYPE_JP2));
	    header('Content-Disposition: attachment; filename="'.basename($filepath).'"');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate');
	    header('Pragma: public');
	    header("Content-Encoding: none");
	    header('Content-Length: ' . filesize($filepath));
	    @readfile($filepath) or die("File not found.");
	    exit;
    }

    /**
     * Convert a URL from the 'http' protocol to 'jpip'
     *
     * @param string $filepath    Location of JPX file
     * @param string $jp2Dir      The JPEG 2000 archive root directory
     * @param string $jpipBaseURL The JPIP Server base URL
     *
     * @return string A JPIP URL.
     */
    private function _getJPIPURL($filepath, $jp2Dir=HV_JP2_DIR,
        $jpipBaseURL = HV_JPIP_ROOT_URL) {

        $webRootRegex = '/'.preg_replace("/\//", "\/", $jp2Dir).'/';
        $jpip = preg_replace($webRootRegex, $jpipBaseURL, $filepath);

        return $jpip;
    }

    /**
     * launch JHelioviewer
     *
     * @return void
     */
    public function launchJHelioviewer () {

        $args = array($this->_params['startTime'], $this->_params['endTime'],
                      $this->_params['imageScale'], $this->_params['layers']);

        header('content-type: application/x-java-jnlp-file');
        header('content-disposition: attachment; filename="JHelioviewer.jnlp"');
        echo '<?xml version="1.0" encoding="utf-8"?>' . "\n";
?>
            <jnlp spec="1.0+" codebase="http://achilles.nascom.nasa.gov/~dmueller/jhv/" href="JHelioviewer.jnlp">
                <information>
                    <title>JHelioviewer</title>
                    <vendor>ESA</vendor>
                    <homepage href="index.html" />
                    <description>JHelioviewer web launcher</description>
                    <offline-allowed />
                </information>

                <resources>
                    <j2se version="1.5+" max-heap-size="1000M"/>
                    <jar href="JHelioviewer.jar" />
                </resources>

                <security>
                    <all-permissions />
                </security>

                <application-desc main-class="org.helioviewer.JavaHelioViewer">
                    <argument>-jhv</argument>
                    <argument><?php vprintf("[startTime=%s;endTime=%s;linked=true;imageScale=%f;imageLayers=%s]", $args); ?></argument>
                </application-desc>
            </jnlp>
<?php
    }


    public function getValidationRules(): array {
        switch($this->_params['action']) {

        case 'getJP2Image':
            $expected = array(
               'optional' => array('jpip', 'json'),
               'bools'    => array('jpip', 'json'),
               'dates'    => array('date')
            );

            // If imageId is specified, that is all that is needed
            if ( isset($this->_params['id']) ) {
                $expected['required'] = array('id');
                $expected['ints']     = array('id');
            }
            else {
                $expected['required'] = array('date', 'sourceId');
                $expected['ints']     = array('sourceId');
            }
            break;

        case 'getJPX':
            $expected = array(
                'required' => array('startTime', 'endTime', 'sourceId'),
                'optional' => array('cadence', 'jpip', 'linked', 'verbose'),
                'bools'    => array('jpip', 'verbose', 'linked'),
                'dates'    => array('startTime', 'endTime'),
                'ints'     => array('cadence', 'sourceId')
            );
            break;

        case 'getJPXClosestToMidPoint':
            $expected = array(
                'required'   => array('sourceId','startTimes', 'endTimes'),
                'optional'   => array('jpip', 'linked', 'verbose', 'cadence'),
                'bools'      => array('jpip', 'verbose', 'linked'),
                'ints'       => array('sourceId', 'cadence'),
                'array_ints' => array('startTimes', 'endTimes')
            );
            break;

        case 'launchJHelioviewer':
            $expected = array(
                'required' => array('startTime', 'endTime', 'imageScale',
                                    'layers'),
                'floats'   => array('imageScale'),
                'dates'    => array('startTime', 'endTime'),
                'layer'    => array('layers')
            );
            break;

        default:
            $expected = array();
            break;
        } // end switch block
        return $expected;
    }

    /**
     * Validate the requested action and associated input parameters.
     *
     * @return void
     */
    public function validate() {
        $expected = $this->getValidationRules();

        Sentry::setContext('Helioviewer', [
            'validation_rules' => $expected
        ]);

        Validation_InputValidator::checkInput($expected, $this->_params,$this->_options);

        return true;
    }
}
?>
