<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_JPEG2000_JP2Image class definition
 * JPEG 2000 Image class
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 *
 * @TODO: Extend Exception class to create more useful objects.
 * @TODO: Use different name for intermediate PNG than final version.
 * @TODO: Forward request to secondary server if it fails for a valid tile?
 * @TODO: build up a "process log" string for each tile process which can be
 *        output to the log in case of failure.
 */
class Image_JPEG2000_JP2Image {

    private $_file;
    private $_width;
    private $_height;
    private $_scale;

    /**
     * Creates a new Image_JPEG2000_JP2Image instance
     *
     * @param string $file   Location of the JPEG 2000 image to work with
     * @param int    $width  JP2 image width
     * @param int    $height JP2 image height
     * @param float  $scale  JP2 image plate-scale
     */
    public function __construct($file, $width, $height, $scale) {
        $this->_file   = $file;
        $this->_width  = $width;
        $this->_height = $height;
        $this->_scale  = $scale;
    }

    /**
     * Returns the JPEG 2000 image path
     *
     * @return string image path
     */
    public function getFilePath() {
        return $this->_file;
    }

    /**
     * Returns the JPEG 2000 image's native plate-scale
     *
     * @return float image scale
     */
    public function getScale() {
        return $this->_scale;
    }

    /**
     * Returns the JPEG 2000 image's native width
     *
     * @return int image width
     */
    public function getWidth() {
        return $this->_width;
    }

    /**
     * Returns the JPEG 2000 image's native height
     *
     * @return int image height
     */
    public function getHeight() {
        return $this->_height;
    }

    /**
     * Extract a region using kdu_expand
     *
     * @param string $outputFile  Location to output file to.
     * @param array  $roi         An array representing the rectangular
     *                            region of interest (roi).
     * @param int    $scaleFactor Difference between the JP2's natural
     *                            resolution and the requested resolution, if
     *                            the requested resolution is less than the
     *                            natural one.
     *
     * @TODO: Should precision of -reduce be limited in same manner as
     *        region strings? (e.g. MDI @ zoom-level 9)
     *
     * @return String - outputFile of the expanded region
     */
    public function extractRegion($outputFile, $roi, $scaleFactor=0) {

        $cmd = HV_KDU_EXPAND . ' -i '.$this->_file.' -o '.$outputFile.' ';

        // Case 1: JP2 image resolution = desired resolution
        // Nothing special to do...

        // Case 2: JP2 image resolution > desired resolution (use -reduce)
        if ( $scaleFactor > 0 )
            $cmd .= '-reduce '.$scaleFactor.' ';

        // Case 3: JP2 image resolution < desired resolution
        // Don't do anything...

        // Add desired region
        $cmd .= $this->_getRegionString($roi);


        $attempts = 0;

        // Attempt JP2 extraction. If the command fails, retry up to two times
        while ( $attempts < 3 ) {
            // Execute the command
            $result = exec(escapeshellcmd($cmd) . " 2>&1", $out, $ret);

            // Succesfull conversions should have return code 0 and 6 lines
            // of output. If either of these conditions are not true the
            // process has likely failed
            if ( $ret == 0 && sizeof($out) <= 6 ) {
                return;
            }
            $attempts += 1;
            usleep(200000); // wait 0.2s
        }

        // If the extraction fails after three attempts, log error
        $msg = sprintf(
                'Error extracting JPEG 2000 subfield region!' .
                "\n\nCOMMAND:\n%s\n\nRETURN VALUE:%d\n\nOUTPUT:\n%s",
                escapeshellcmd($cmd),
                $ret,
                implode("\n", $out) );

        throw new Exception($msg, 14);
    }

    /**
     * Builds a region string to be used by kdu_expand.
     * e.g. "-region {0.0,0.0},{0.5,0.5}"
     *
     * NOTE: Because kakadu's internal precision for region strings is less
     * than PHP, the numbers used are cut off to prevent erronious rounding.
     *
     * @param array $roi The requestd region of interest.
     *
     * @return string A kdu_expand -region formatted sub-command.
     */
    private function _getRegionString($roi) {
        $precision = 6;

        $top    = $roi['top'];
        $left   = $roi['left'];
        $bottom = $roi['bottom'];
        $right  = $roi['right'];

        // Calculate the top, left, width, and height in terms of kdu_expand
        // parameters (between 0 and 1)
        $scaledTop    = $top  / $this->_height;
        $scaledLeft   = $left / $this->_width;
        $scaledHeight = ($bottom - $top) / $this->_height;
        $scaledWidth  = ($right - $left) / $this->_width;

        // Ensure no negative exponents.
        $scaledTop    = substr(
            $scaledTop    > 0.0001 ? $scaledTop    : 0, 0, $precision );
        $scaledLeft   = substr(
            $scaledLeft   > 0.0001 ? $scaledLeft   : 0, 0, $precision );
        $scaledHeight = substr(
            $scaledHeight > 0.0001 ? $scaledHeight : 0, 0, $precision );
        $scaledWidth  = substr(
            $scaledWidth  > 0.0001 ? $scaledWidth  : 0, 0, $precision );

        $region = '-region {'.$scaledTop   .','.$scaledLeft .'},'
                         .'{'.$scaledHeight.','.$scaledWidth.'}';

        return $region;
    }
}
?>
