<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_ImageType_RHESSIImage class definition
 * There is one xxxImage for each type of detector Helioviewer supports.
 *
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

class Image_ImageType_RHESSIImage extends Image_HelioviewerImage {
    /**
     * Creates a new RHESSIImage
     *
     * @param string $jp2      Source JP2 image
     * @param string $filepath Location to output the file to
     * @param array  $roi      Top-left and bottom-right pixel coordinates on the image
     * @param array  $uiLabels Datasource label hierarchy
     * @param int    $offsetX  Offset of the sun center from the image center
     * @param int    $offsetY  Offset of the sun center from the iamge center
     * @param array  $options  Optional parameters
     *
     * @return void
     */
    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options) {
        $color_table = HV_ROOT_DIR . "/resources/images/color-tables/RHESSI.png";
        $this->setColorTable($color_table);
        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }
}
?>
