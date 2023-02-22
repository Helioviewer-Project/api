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
    private $wavelength;
    private $availableWavelengths;
    private $dataMax;
    private $date;
    private $localBrightness;

   /**
    * Creates a new ImageBrightness instance
      * 
      * @return void
      */
   public function __construct($datetime,$wavelength) {

      $this->wavelength = $wavelength;
       
      //from aia_rescaling_data.json
      $this->availableWavelengths = array("304");

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
         $this->type = "rms";
         $this->_findLocalBrightness();
      }else{
         $this->localBrightness = null;
      }

      if($this->localBrightness != null){
         $this->_computeScalar();
      }
      
   }

   private function _findLocalBrightness(){
      $filePath = HV_ROOT_DIR . '/resources/JSON/aia_correction_data_304.json';
      $file = json_decode(file_get_contents($filePath));
      $firstDate = "2010-06-02";
      $lastDate = "2019-09-03";
      if($file != null){
         if($this->date < $firstDate){
            $this->localBrightness = $file->{$firstDate}->{$this->type};
         }else if($this->date > $lastDate){
            $this->localBrightness = $file->{$lastDate}->{$this->type};
         }else{
            $this->localBrightness = $file->{$this->date}->{$this->type};
         }
         $this->startOfMissionBrightness = $file->{"max"}->{"rms"};
      }
   }

   private function _computeScalar(){
      $this->ratio = $this->startOfMissionBrightness / $this->localBrightness;
      $this->brightness = 1 + log10($this->ratio);
   }

   public function getBrightness(){
      if(in_array($this->wavelength,$this->availableWavelengths)){
         return $this->brightness;
      }else{
         return 1;
      }
   }

}

?>
