<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_HelioviewerImage class definition
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Jaclyn Beck <jaclyn.r.beck@gmail.com>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once 'SubFieldImage.php';

class Image_HelioviewerImage extends Image_SubFieldImage {

    protected $uiLabels;
    protected $filepath;
    protected $options;

    /**
     * Constructor
     *
     * @param string $jp2           Original JP2 image from which the subfield
     *                              should be derrived
     * @param string $filepath      Location to output the file to
     * @param array  $roi           Subfield region of interest in pixels
     * @param array  $uiLabels      Datasource label hierarchy
     * @param float  $offsetX       Offset of the sun center from image center
     * @param float  $offsetY       Offset of the sun center from image center
     */
    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options) {

        // Default options
        $defaults = array(
            'date'          => '',
            'compress'      => true,
            'layeringOrder' => 1,
            'opacity'       => 100,
            'palettedJP2'   => false,
            'movie' 	    => false,
            'size' 	        => 0,
            'jp2Difference' => false,
            'jp2DiffPath'   => '',
            'jp2DifferenceLabel'   => '',
            'followViewport' => false
        );
        $this->options = array_replace($defaults, $options);

        $this->uiLabels = $uiLabels;
        $this->filepath    = $filepath;

        $imageSettings = array(
        	'opacity'      => $this->options['opacity'],
        	'movie'        => $this->options['movie'],
        	'size'         => $this->options['size'],
        	'jp2Difference'=> $this->options['jp2Difference'],
        	'jp2DiffPath'  => $this->options['jp2DiffPath'],
        	'jp2DifferenceLabel'  => $this->options['jp2DifferenceLabel'],
            'followViewport' => $this->options['followViewport']
        );

        parent::__construct($jp2, $roi, $this->filepath, $offsetX, $offsetY, $imageSettings);

        $padding = $this->computePadding($roi);
        $this->setPadding($padding);

        if ( HV_DISABLE_CACHE || $this->_imageNotInCache() || $this->options['jp2Difference'] != false) {
            $this->build();
        }
    }

    /**
     * Determines if the roi is invalid by calculating width and height and
     * seeing if they are less than 0.
     *
     * @param Array $pixelRoi An array with values for top, left, bottom,
     *                        and right
     *
     * @return boolean
     */
    private function _imageNotVisible($pixelRoi) {
        return ($pixelRoi['bottom'] - $pixelRoi['top']  <= 1) ||
               ($pixelRoi['right']  - $pixelRoi['left'] <= 1);
    }

    /**
     * Gets a string that will be displayed in the image's watermark
     *
     * @return string watermark name
     */
    public function getWaterMarkName() {
        return $this->getWaterMarkName();
    }

    /**
     * Gets the timestamp that will be displayed in the image's watermark
     *
     * @return string date
     */
    public function getWaterMarkDateString() {
        // Add extra spaces between date and time for readability.
        return str_replace('T', '   ', $this->options['date']) . $this->options['jp2DifferenceLabel']. "\n";
    }

    /**
     * Get the layering order
     *
     * @return int layeringOrder
     */
    public function getLayeringOrder() {
        return $this->options['layeringOrder'];
    }

    /**
     * Get opacity
     *
     * @return float opacity
     */
    public function getOpacity() {
        return $this->options['opacity'];
    }

    /**
     * Check to see if the image is cached
     *
     * @return boolean
     */
    private function _imageNotInCache() {
        return !@file_exists($this->filepath);
    }


    /**
     * Gets the filepath
     *
     * @return string outputFile
     */
    public function getFilepath() {
        return $this->filepath;
    }

    /**
     * Sets a new filepath
     *
     * @param string $filePath New filepath
     *
     * @return void
     */
    public function setNewFilePath($filePath) {
        $this->setNewFilePath($filePath);
    }
}
?>
