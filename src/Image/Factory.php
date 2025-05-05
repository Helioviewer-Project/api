<?php declare(strict_types=1);
/**
 * Image_Factory class definition
 *
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Image_Factory {
    /**
     * Returns the appropriate image class to use for the given image information
     * @param array $image - Image data returned by Img_Index:getImageInformation
     * @return string Image Class name
     */
    static function getImageClass(array $image): string {
        // TODO 2011/04/18: Generalize process of choosing class to use
        //error_log(json_encode($image['uiLabels']));
        if ( count($image['uiLabels']) >= 3
          && $image['uiLabels'][1]['name'] == 'SECCHI' ) {

            if ( substr($image['uiLabels'][2]['name'], 0, 3) == 'COR' ) {
                $type = 'CORImage';
            }
            else {
                $type = strtoupper($image['uiLabels'][2]['name']).'Image';
            }
        }
        else if ($image['uiLabels'][0]['name'] == 'TRACE') {
            $type = strtoupper($image['uiLabels'][0]['name']).'Image';
        }
        else if ($image['uiLabels'][0]['name'] == 'Hinode') {
            $type = 'XRTImage';
        }
        else if ($image['uiLabels'][0]['name'] == 'RHESSI') {
            $type = 'RHESSIImage';
        }
        else if ($image['uiLabels'][0]['name'] == 'PUNCH') {
            $type = 'PUNCHImage';
        }
        else if (count($image['uiLabels']) >=2) {
            $type = strtoupper($image['uiLabels'][1]['name']).'Image';
        }

        include_once HV_ROOT_DIR.'/../src/Image/ImageType/'.$type.'.php';
        $classname = 'Image_ImageType_'.$type;
        return $classname;
    }
};
