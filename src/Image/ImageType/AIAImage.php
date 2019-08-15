<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Image_ImageType_AIAImage class definition
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

class Image_ImageType_AIAImage extends Image_HelioviewerImage {
    /**
     * Creates a new AIAImage
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
		
		/*
			Temporary fix to use correct color table. Because both v2 and v3 runnning at same time some 
			names have different parameters
		*/
		$name = $uiLabels[2]['name'];
		if($uiLabels[2]['name'] == 'AIA'){
			$name = $uiLabels[3]['name'];
		}
		
        $colorTable = HV_ROOT_DIR
                    . '/resources/images/color-tables/'
                    . 'SDO_AIA_'.$name.'.png';
        $this->setColorTable($colorTable);
        
        //compute image brightness rescaling factor to correct for sensor degradation
        include_once HV_ROOT_DIR.'/../src/Helper/ImageBrightness.php';
        $brightnessHelper = new Helper_ImageBrightness($options['date'],$name);
        $this->setImageBrightnessScalar($brightnessHelper->getBrightness());

        parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
    }

    /**
     * Gets a string that will be displayed in the image's watermark
     *
     * @return string watermark name
     */
    public function getWaterMarkName() {
        $name = $this->uiLabels[2]['name'];
		if($this->uiLabels[2]['name'] == 'AIA'){
			$name = $this->uiLabels[3]['name'];
		}
        return 'AIA '.$name."\n";
    }
}
?>