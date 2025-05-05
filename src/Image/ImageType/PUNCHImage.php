<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_ImageType_PUNCHImage class definition
 * There is one xxxImage for each type of detector Helioviewer supports.
 *
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

class Image_ImageType_PUNCHImage extends Image_HelioviewerImage {
    /**
     * Creates a new PUNCHImage
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
        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }

    /**
     * Apply opacity or the occulter depending on options.
     */
    protected function setAlphaChannel(&$imagickImage) {
        $this->applyOpacity($imagickImage);
    }

    protected function applyOpacity(IMagick &$imagickImage) {
        if ($this->options['opacity'] < 100) {
            $opacity = $this->imageOptions['opacity'] / 100;
            try {
                @$imagickImage->setImageOpacity($opacity);
            } catch (Throwable $e) {
                $imagickImage->setImageAlpha($opacity);
            }
        }
    }
}
?>
