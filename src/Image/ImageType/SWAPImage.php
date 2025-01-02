<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_ImageType_SWAPImage class definition
 * There is one xxxImage for each type of detector Helioviewer supports.
 *
 * @category Image
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @author   Keith Hughitt <keith.hughitt@nasa.gov>
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

class Image_ImageType_SWAPImage extends Image_HelioviewerImage {
    /**
     * Creates a new SWAPImage
     *
     * @param string $jp2      Source JP2 image
     * @param string $filepath Location to output the file to
     * @param array  $roi      Top-left and bottom-right pixel coordinates
     *                         on the image
     * @param array  $uiLabels Datasource label hierarchy
     * @param int    $offsetX  Offset of the sun center from the image center
     * @param int    $offsetY  Offset of the sun center from the iamge center
     * @param array  $options  Optional parameters
     *
     * @return void
     */
    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options) {

        $colorTable = HV_ROOT_DIR
                    . '/resources/images/color-tables'
                    . '/PROBA2_SWAP_174.png';

        $imgDate = strtotime($options['date']);

        // Handle SWAP 174's change from built-in color palette to grayscale
        if ( $imgDate < strtotime('2013-06-27 00:00:00') ) {
            $options['palettedJP2'] = true;
        }
        else if ( $imgDate < strtotime('2013-06-27 23:59:59') )  {
            // Assume the image is grayscale, but
            // flag it for verification once the image is loaded into an
            // imagemagick object within SubFieldImage.php
            $options['palettedJP2'] = false;
            $options['verifyGrayscale'] = true;
        }
        else {
            $options['palettedJP2'] = false;
        }


        if ( $options['palettedJP2'] === false && @file_exists($colorTable) ) {
            $this->setColorTable($colorTable);
        }
        else {
            $this->setColorTable(false);
            $options['palettedJP2'] = true;
        }

        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }
}
?>
