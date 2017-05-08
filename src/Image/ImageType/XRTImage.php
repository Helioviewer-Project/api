<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_ImageType_XRTImage class definition
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

class Image_ImageType_XRTImage extends Image_HelioviewerImage {
    /**
     * Creates a new XRTImage
     *
     * @param string $jp2                    Source JP2 image
     * @param string $filepath               Location to output the file to
     * @param array  $roi                    Top-left and bottom-right pixel
     *                                       coordinates on the image
     * @param array  $uiLabels
     * @param int    $offsetX                Offset of the sun center from the
     *                                       image center
     * @param int    $offsetY                Offset of the sun center from the
     *                                       image center
     * @param array  $options                Optional parameters
     * @param array  $sunCenterOffsetParams  Header keywords for Sun center
     *                                       offset calculation
     *
     * @return void
     */
    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options, $sunCenterOffsetParams, $name = '') {

        $this->uiLabels = $uiLabels;
		$this->name = $name;
		
        $colorTable = HV_ROOT_DIR.'/resources/images/color-tables/Hinode_XRT.png';

        if (file_exists($colorTable)) {
            $this->setColorTable($colorTable);
        }
        else {
            $this->setColorTable(false);
        }

        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }

    /**
     * Gets a string that will be displayed in the image's watermark
     *
     * @return string watermark name
     */
    public function getWaterMarkName() {
        //return 'XRT '.$this->uiLabels[2]['name'].' ' .(isset($this->uiLabels[3]['name']) ? $this->uiLabels[3]['name'] : '')."\n";
        return $this->name ."\n";
    }
}
