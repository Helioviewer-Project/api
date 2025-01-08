<?php
/**
 * ImageType_GONGImage class definition
 * There is one xxxImage for each type of detector Helioviewer supports.
 *
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

class Image_ImageType_GONGImage extends Image_HelioviewerImage {
    /**
     * Creates a new GONGImage
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

        // GONG has no color table
        $this->setColorTable(false);
        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }

    /**
     * GONG does not use a color table; Do nothing.
     *
     * @param string $input Image to apply color table to
     *
     * @return void
     */
    protected function setColorPalette(&$input) {
        return false;
    }

}
?>
