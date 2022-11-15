<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_JPEG2000_JP2ImageXMLBox class definition
 * JPEG 2000 Image XML Box parser class
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Image_JPEG2000_JP2ImageXMLBox {

    private $_file;
    private $_xml;
    private $_array;

    /**
     * Create an instance of Image_JPEG2000_JP2Image_XMLBox
     *
     * @param string $file JPEG 2000 Image location
     * @param string $root Where the data is coming from
     */
    public function __construct($file, $root='fits') {
        $this->_file = $file;
        $this->getXMLBox($root);
    }

    /**
     * Given a filename and the name of the root node, extracts
     * the XML header box from a JP2 image
     *
     * @param string $root Name of the XMLBox root node (if known)
     *
     * @return void
     */
    public function getXMLBox($root) {

        if ( !@file_exists($this->_file) ) {
            $msg = 'File does not exist.  Check cache directory permissions.';
            throw new Exception($msg, 50);
        }

        $fp = @fopen($this->_file, 'rb');

        $xml  = '';

        // Read file until header has been retrieved
        while ( !@feof($fp) ) {
            $line = @fgets($fp);
            $xml .= $line;

            if ( strpos($line, '</'.$root.'>') !== false ) {
                break;
            }
        }
        $start = strpos($xml, '<'.$root.'>');
        $end   = strpos($xml, '</'.$root.'>') + strlen('</'.$root.'>');

        $xml = substr($xml, $start, $end-$start);

        @fclose($fp);

        // Work-around Feb 24, 2012: escape < and >
        $xml = str_replace(' < ', ' &lt; ',
                   str_replace(' > ', ' &gt; ', $xml)
               );

        $this->_xmlString = '<?xml version="1.0" encoding="utf-8"?>'."\n"
                          . $xml;

        //$this->_xml = new DOMDocument();

        //$this->_xml->loadXML($this->_xmlString);

        $this->_xml = simplexml_load_string($this->_xmlString);
        $json_string = json_encode($this->_xml);
		$this->_array = json_decode($json_string, TRUE);
    }

    /**
     * Returns the XML header as a string
     */
    public function getXMLString() {
        return $this->_xmlString;
    }

    /**
     * Prints xml information
     *
     * @return void
     */
    public function printXMLBox() {
        header('Content-type: text/xml');
        echo $this->_xmlString;
    }

    /**
     * WORKAROUND FOR INVALID DSUN:
     * There are some images which have an invalid DSun
     * value which results in an incorrect imageScale.
     * The result is that helioviewer believes the
     * image to be an extremely high resolution and attempts to scale the image
     * down to a point that image magick can't process it.
     * In order to handle these images, this
     * workaround attempts to detect those invalid dsun values
     * and override them to a relatively safe DSUN value.
     * Per Bogdan's remark: https://github.com/Helioviewer-Project/api/issues/194#issuecomment-1203147423
     * We will use a DSUN threshold of 0.04 AUs, since we don't expect any telescopes
     * to get that close anytime soon.
     */
    private function _workaroundInvalidDsun($dsun) {
        $DSUN_OVERRIDE_THRESHOLD = HV_CONSTANT_AU * 0.04;
        if ($dsun <= $DSUN_OVERRIDE_THRESHOLD) {
            // Add an informational log to let us know when this happens. It is expected
            // in some older files, but not new ones.
            $inst = $this->_getElementValue('INSTRUME');
            $date = $this->_getElementValue('DATE');
            error_log("Patching dsun value to 1AU. Instrument: $inst, date: $date, Old dsun: $dsun");
            // Using 1AU as the default dsun since this
            // was already being used as a value for other
            // cases where dsun is invalid.
            return HV_CONSTANT_AU;
        }
        return $dsun;
    }

    public function getRSun() {
        try {
            // First, try to return the R_SUN value from FITS
            return $this->_getElementValue('R_SUN');
        } catch (Exception $e) {
            // If R_SUN is not in the header, then try to compute the R_SUN based on DSUN.
            // Get DSUN first since it contains logic for correcting FITS data for different sources.
            $dsun = $this->getDSun();
            // Safe to get CDELT1 here. If CDELT1 doesn't exist, then getDSun will return an error.
            $scale = $this->_getElementValue('CDELT1');
            // Unroll the DSUN formula to solve for RSUN
            // $dsun = (HV_CONSTANT_RSUN / ($rsun * $scale)) * HV_CONSTANT_AU;
            $rsun = (HV_CONSTANT_AU * HV_CONSTANT_RSUN) / ($dsun * $scale);
            return $rsun;
        }
    }

    /**
     * Returns the distance to the sun in meters
     *
     * For images where dsun is not specified it can be determined using:
     *
     *    dsun = (rsun_1au / rsun_image) * dsun_1au
     */
    public function getDSun() {
        $maxDSUN = 2.25e11; // A reasonable max for solar observatories
                            // ~1.5 AU

        try {
            // AIA, EUVI, COR, SWAP, SXT
            $dsun = $this->_getElementValue('DSUN_OBS');
        }
        catch (Exception $e) {
            try {
                // EIT
                $rsun = $this->_getElementValue('SOLAR_R');
            }
            catch (Exception $e) {
                try {
                    // MDI
                    $rsun = $this->_getElementValue('RADIUS');
                }
                catch (Exception $e) {
                    //
                }
            }

            if ( isset($rsun) ) {
                $scale = $this->_getElementValue('CDELT1');
                if ( $scale == 0 ) {
                    throw new Exception(
                        'Invalid value for CDELT1: '.$scale, 15);
                }
                if ( $rsun == 0 ) {
                    throw new Exception(
                        'Invalid value for RSUN: '.$rsun, 15);
                }
                $dsun = (HV_CONSTANT_RSUN / ($rsun * $scale))
                        * HV_CONSTANT_AU;
            }
        }

        // HMI continuum images may have DSUN = 0.00
        // LASCO/MDI may have rsun=0.00
        if ( !isset($dsun) || $dsun <= 0 ) {
            $dsun = HV_CONSTANT_AU;
        }

        // A little more DSUN validation in the case that
        // dsun is erroneously small, but not 0.
        $dsun = $this->_workaroundInvalidDsun($dsun);

        // Check to make sure header information is valid
        if ( (filter_var($dsun, FILTER_VALIDATE_FLOAT) === false) ||
             ($dsun <= 0) ||
             ($dsun >= $maxDSUN) ) {

            throw new Exception('Invalid value for DSUN: '.$dsun, 15);
        }

        return $dsun;
    }

    /**
     * Returns the dimensions for a given image
     *
     * @return array JP2 width and height
     */
    public function getImageDimensions() {
        try {
            $width  = $this->_getElementValue('NAXIS1');
            $height = $this->_getElementValue('NAXIS2');
        }
        catch (Exception $e) {
            throw new Exception(
                'Unable to locate image dimensions in header tags!', 15);
        }

        return array($width, $height);
    }

    /**
     * Returns the plate scale for a given image
     *
     * @return string JP2 image scale
     */
    public function getImagePlateScale() {
        try {
            $scale = $this->_getElementValue('CDELT1');
        }
        catch (Exception $e) {
            throw new Exception(
                'Unable to locate image scale in header tags!');
        }

        // Check to make sure header information is valid
        if ( (filter_var($scale, FILTER_VALIDATE_FLOAT) === false) ||
             ($scale <= 0) ) {

            throw new Exception('Invalid value for CDELT1: '.$scale, 15);
        }

        return $scale;
    }

    /**
     * Returns the coordinates for the image's reference pixel.
     *
     * NOTE: The values for CRPIX1 and CRPIX2 reflect the x and y coordinates
     *       with the origin at the bottom-left corner of the image, not the
     *       top-left corner.
     *
     * @return array Pixel coordinates of the reference pixel
     */
    public function getRefPixelCoords() {
        try {
            if ( $this->_getElementValue('INSTRUME') == 'XRT' ) {
            	$x = -($this->_getElementValue('CRVAL1') / $this->_getElementValue('CDELT1') - $this->_getElementValue('CRPIX1'));
				$y = -($this->_getElementValue('CRVAL2') / $this->_getElementValue('CDELT2') - $this->_getElementValue('CRPIX2'));
			}else{
				$x = $this->_getElementValue('CRPIX1');
				$y = $this->_getElementValue('CRPIX2');
			}
        }
        catch (Exception $e) {
            throw new Exception(
                'Unable to locate reference pixel coordinates in header tags!',
                15);
        }

        return array($x, $y);
    }

    /**
     * Returns the Header keywords containing any Sun-center location
     * information
     *
     * @return array Header keyword/value pairs from JP2 file XML
     */
    public function getSunCenterOffsetParams() {
        $sunCenterOffsetParams = array();

        try {
            if ( $this->_getElementValue('INSTRUME') == 'XRT' ) {
                $sunCenterOffsetParams['XCEN'] =
                    $this->_getElementValue('XCEN');
                $sunCenterOffsetParams['YCEN'] =
                    $this->_getElementValue('YCEN');
                $sunCenterOffsetParams['CDELT1'] =
                    $this->_getElementValue('CDELT1');
                $sunCenterOffsetParams['CDELT2'] =
                    $this->_getElementValue('CDELT2');
            }
        }
        catch (Exception $e) {
            throw new Exception(
                'Unable to locate Sun center offset params in header!', 15);
        }

        return $sunCenterOffsetParams;
    }

    /**
     * Returns layering order based on data source
     *
     * NOTE: In the case of Hinode XRT, layering order is decided on an
     *       image-by-image basis
     *
     * @return integer layering order
     */
    public function getLayeringOrder() {
        try {
            switch ($this->_getElementValue('TELESCOP')) {
                case 'SOHO':
                    $layeringOrder = 2;     // SOHO LASCO C2
                    if ( $this->_getElementValue('INSTRUME') == 'EIT' ) {
                        $layeringOrder = 1;  // SOHO EIT
                    }
                    else if ( $this->_getElementValue('INSTRUME') == 'MDI' ) {
                        $layeringOrder = 1;  // SOHO MDI
                    }
                    else if ( $this->_getElementValue('DETECTOR') == 'C3' ) {
                        $layeringOrder = 3; // SOHO LASCO C3
                    }
                    break;
                case 'STEREO':
                    $layeringOrder = 2;     // STEREO_A/B SECCHI COR1
                    if ( $this->_getElementValue('DETECTOR') == 'COR2' ) {
                        $layeringOrder = 3; // STEREO_A/B SECCHI COR2
                    }
                    break;
                case 'HINODE':
                    $layeringOrder = 1;     // Hinode XRT full disk
                    if (   $this->_getElementValue('NAXIS1')
                         * $this->_getElementValue('CDELT1') < 2048.0
                        &&
                           $this->_getElementValue('NAXIS2')
                         * $this->_getElementValue('CDELT2') < 2048.0 ) {

                        $layeringOrder = 2; // Hinode XRT sub-field
                    }
                    break;
                default:
                    // All other data sources
                    $layeringOrder = 1;
            }
        } catch (Exception $e) {
            throw new Exception(
                'Unable to determine layeringOrder from header tags!', 15);
        }

        return $layeringOrder;
    }

    /**
     * Returns true if the image was rotated 180 degrees
     *
     * Note that while the image data may have been rotated to make it easier
     * to line up different data sources, the meta-information regarding the
     * sun center, etc. are not adjusted, and thus must be manually adjusted
     * to account for any rotation.
     *
     * @return boolean True if the image has been rotated
     */
    public function getImageRotationStatus() {
        try {
            $rotation = $this->_getElementValue('CROTA1');
            if ( abs($rotation) > 170 ) {
                return true;
            }
        }
        catch (Exception $e) {
            // AIA, EIT, and MDI do their own rotation
            return false;
        }
    }

    /**
     * Retrieves the value of a unique dom-node element or returns false if
     * element is not found, or more than one is found.
     *
     * @param string $name The name of the XML element whose value should
     *                     be return
     *
     * @return string the value for the specified element
     */
    private function _getElementValue($name) {

        if (isset($this->_array[$name])) {
	        $value = $this->_array[$name];
            if ( !is_null($value) ) {
                return $value;
            }
        }

        /*$element = $this->_xml->getElementsByTagName($name);

        if ($element) {
            if ( !is_null($element->item(0)) ) {
                return $element->item(0)->childNodes->item(0)->nodeValue;
            }
        }*/

        throw new Exception('Element not found', 15);
    }
}
?>
