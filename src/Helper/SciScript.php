<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Science Data Download Script Generator
 *
 * PHP version 5
 *
 * @category Helper
 * @package  Helioviewer
 * @author   Jeff Stys <jeff.stys@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     http://launchpad.net/helioviewer.org
 *
 */
class Helper_SciScript {

    protected $_params;
    protected $_imageLayers;
    protected $_start;
    protected $_end;

    public function __construct($params) {
        $this->_params  = $params;

        // Parse datasource string into arrays
        if ( preg_match('/^([\[\]\,0-9]+)$/', $this->_params['sourceIds']) !== 1 ) {
            throw new Exception("Invalid value for 'sourceIds'.", 23);
        }
        $this->_imageLayers = $this->_parseSourceIds($this->_params['sourceIds']);

        // Handle date format
        $this->_start = str_replace(Array('T','Z'), Array(' ',''), $this->_params['startDate']);
        $this->_end = str_replace(Array('T','Z'), Array(' ',''), $this->_params['endDate']);
    }

    protected function _parseSourceIds($sourceIds) {
        $sourceIds = trim($sourceIds, ' []');

        $sourceIdArray = explode(',', $sourceIds);

        if ( count($sourceIdArray) > 0 && $sourceIdArray[0] != '' ) {
            include_once HV_ROOT_DIR.'/../src/Database/ImgIndex.php';
            $imgIndex = new Database_ImgIndex();

            foreach ($sourceIdArray as $i => $sourceId) {
                $info = $imgIndex->getDatasourceInformationFromSourceId($sourceId);

                $imageLayersArray[$i] = Array(
                    'uiLabels' => $info['uiLabels']
                );
            }
        }
        else {
            throw new Exception("Parameter 'sourceIds' must contain one or more data source identifiers.", 23);
        }

        return $imageLayersArray;
    }


    /**
     * Returns parameters for SDO cut-out service
     */
    protected function _getCutoutParams() {

        if ( array_key_exists('x0',$this->_params) &&
             array_key_exists('y0',$this->_params) &&
             array_key_exists('width',$this->_params) &&
             array_key_exists('height',$this->_params) &&
             array_key_exists('imageScale',$this->_params)) {

            // arcsec
            $xcen = $this->_params['x0'];
            $ycen = $this->_params['y0'];
            $fovx = $this->_params['width'] *$this->_params['imageScale'];
            $fovy = $this->_params['height']*$this->_params['imageScale'];
        }
        else if ( array_key_exists('x1',$this->_params) &&
                  array_key_exists('y1',$this->_params) &&
                  array_key_exists('x2',$this->_params) &&
                  array_key_exists('y2',$this->_params) ) {

            // arcsec
            $xcen = ($this->_params['x1']+$this->_params['x2'])/2.0;
            $ycen = ($this->_params['y1']+$this->_params['y2'])/2.0;
            $fovx = ($this->_params['x2']-$this->_params['x1']);
            $fovy = ($this->_params['y2']-$this->_params['y1']);
        }
        else {
            throw new Exception(
                "Region of interest not defined: you must specify values " .
                "for either [x1, x2, y1, y2] " .
                "or [imageScale, x0, y0, width, height].", 23);
        }

        // Enforce minimum cut-out size
        $fovx = (($fovx < 240) ? 240 : $fovx);
        $fovy = (($fovy < 240) ? 240 : $fovy);

        return Array($xcen,$ycen,$fovx,$fovy);
    }


    /**
     * Returns generated script as a file attachment
     */
    protected function _printScript($filename, $body) {
        $file_extension = pathinfo($filename, PATHINFO_EXTENSION);

        // Set HTTP headers
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Cache-Control: private", false); // required for certain browsers
        header("Content-Disposition: attachment; filename=\"" . $filename . "\"");
        header("Content-Transfer-Encoding: binary");
        header("Content-Length: " . mb_strlen($body));
        switch ($file_extension) {
            case 'pro':
                header("Content-type: text/x-rsiidl-src");
                break;
            case 'py':
                header("Content-type: text/x-script.python");
                break;
            default:
                header("Content-type: text/plain");
        }

        echo $body;
    }
}
?>
