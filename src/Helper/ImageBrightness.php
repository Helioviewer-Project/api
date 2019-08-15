<?php
/**
 * Helper_ImageBrightness Class Definition
 * Helper for producing aestheically pleasing images to correct for sensor AIA degradation.
 * Based on data from: https://github.com/mjpauly/aia
 * 
 * @category Helper
 * @package  Helioviewer
 * @author   Kirill Vorobyev <kirill.g.vorobyev@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

class Helper_ImageBrightness {

   /**
    * Creates a new ImageBrightness instance
      * 
      * @return void
      */
   public function __construct($datetime,$wavelength){

      //from aia_rescaling_data.json
      $this->availableWavelengths = array("94","131","171","193","211","304","335");

      //from https://github.com/Helioviewer-Project/jp2gen/blob/master/idl/sdo/aia/hvs_version5_aia.pro
      $this->dataMax = array( "94"  => 30.0,
                              "131" => 500.0,
                              "171" => 14000.0,
                              "193" => 2500.0,
                              "211" => 1500.0,
                              "304" => 250.0,
                              "335" => 80.0);

      $this->date = substr($datetime,0,10);//isolate the date

      if(in_array($wavelength,$this->availableWavelengths)){
         $this->wavelength = $wavelength;
         $this->_findLocalBrightness();
      }else{
         $this->localBrightness = null;
      }

      if($this->localBrightness != null){
         $this->_computeScalar();
      }
      
   }

   private function _findLocalBrightness(){
      $filePath = HV_ROOT_DIR . '/resources/JSON/aia_rescaling_data.json';
      $file = json_decode(file_get_contents($filePath));
      $firstDate = "2010-05-01";
      $lastDate = "2017-05-10";
      if($file != null){
         if($this->date < $firstDate){
            $this->localBrightness = $file->{$firstDate}->{$this->wavelength};
         }else if($this->date > $lastDate){
            $this->localBrightness = $file->{$lastDate}->{$this->wavelength};
         }else{
            $this->localBrightness = $file->{$this->date}->{$this->wavelength};
         }
         $this->startOfMissionBrightness = $file->{$firstDate}->{$this->wavelength};
      }
   }

   private function _computeScalar(){
      $this->ratio = $this->startOfMissionBrightness / $this->localBrightness;
      $this->brightness = 1 + ( log10($this->ratio) / log10($this->dataMax[$this->wavelength]) );
   }

   public function getBrightness(){
      return $this->brightness;
   }

}

?>