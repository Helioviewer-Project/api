#!/usr/bin/env python
#-*- coding:utf-8 -*-
"""Helioviewer.org JP2 Download Daemon (HVPull)
JPEG 2000 Image XML Box parser class
"""
import sys
from xml.etree import cElementTree as ET
import numpy as np
from astropy.time import Time
from sunpy.map import Map, GenericMap
from sunpy.util.xml import xml_to_dict
from sunpy.io._header import FileHeader
from sunpy.map.mapbase import MapMetaValidationError
from glymur import Jp2k
from sunpy.util.xml import xml_to_dict
from typing import Union

__HV_CONSTANT_RSUN__ = 959.644
__HV_CONSTANT_AU__ = 149597870700

class JP2parser:
    _filepath = None
    _data = None

    def __init__(self, path):
        """Main application"""
        self._filepath = path
        # getImageMap initializes self._data
        self._imageData = self._loadSunpyMap()

    def _loadSunpyMap(self) -> GenericMap:
        try:
            return Map(self.read_header_only_but_still_use_sunpy_map())
        except (MapMetaValidationError, ValueError): # ValueError catches a CUNIT with type 'degrees'
            return Map(self.read_header_only_but_still_use_sunpy_map(patch_cunit=True))

    def getImageMap(self) -> GenericMap:
        return self._imageData

    def getData(self):
        """Create data object of JPEG 2000 image.

        Get image observatory, instrument, detector, ∑measurement, date from image
        metadata and create an object.
        """

        imageData = self.getImageMap()
        image = dict()

        #Calculate sun position/size/scale
        dimensions              = self.getImageDimensions();
        refPixel                = self.getRefPixelCoords();
        imageScale              = self.getImagePlateScale();
        dsun                    = self.getDSun();
        layeringOrder           = self.getLayeringOrder();

        # Normalize image scale
        imageScale = imageScale * (dsun / __HV_CONSTANT_AU__);

        image['scale'] = imageScale
        image['width'] = dimensions['width']
        image['height'] = dimensions['height']
        image['refPixelX'] = refPixel['x']
        image['refPixelY'] = refPixel['y']
        image['layeringOrder'] = layeringOrder

        image['DSUN_OBS'] = self._data['DSUN_OBS'] if 'DSUN_OBS' in self._data else 'NULL'
        image['SOLAR_R'] = self._data['SOLAR_R'] if 'SOLAR_R' in self._data else 'NULL'
        image['RADIUS'] = self._data['RADIUS'] if 'RADIUS' in self._data else 'NULL'
        image['NAXIS1'] = self._data['NAXIS1'] if 'NAXIS1' in self._data else 'NULL'
        image['NAXIS2'] = self._data['NAXIS2'] if 'NAXIS2' in self._data else 'NULL'
        image['CDELT1'] = self._data['CDELT1'] if 'CDELT1' in self._data else 'NULL'
        image['CDELT2'] = self._data['CDELT2'] if 'CDELT2' in self._data else 'NULL'
        image['CRVAL1'] = self._data['CRVAL1'] if 'CRVAL1' in self._data else 'NULL'
        image['CRVAL2'] = self._data['CRVAL2'] if 'CRVAL2' in self._data else 'NULL'
        image['CRPIX1'] = self._data['CRPIX1'] if 'CRPIX1' in self._data else 'NULL'
        image['CRPIX2'] = self._data['CRPIX2'] if 'CRPIX2' in self._data else 'NULL'
        image['XCEN'] = self._data['XCEN'] if 'XCEN' in self._data else 'NULL'
        image['YCEN'] = self._data['YCEN'] if 'YCEN' in self._data else 'NULL'
        image['CROTA1'] = self._data['CROTA1'] if 'CROTA1' in self._data else 'NULL'

        #Fix FITS NaN parameters
        for key, value in image.items():
            if self._is_string(value):
                if value.lower() == 'nan' or value.lower() == '-nan':
                    image[key] = 'NULL'

        image['observatory'] = self.get_observatory(imageData)
        image['instrument'] = self.get_instrument(imageData)
        image['detector'] = imageData.detector
        if image['instrument'] == "CCOR1":  # BIG ugly hack because a MAP for CCOR-1 does not exist in map_factory
            imageData.nickname = 'CCOR-1'
        elif image['instrument'] == "CCOR2":
            imageData.nickname = 'CCOR-2'
        # In sunpy V3, the nickname changed to include the filter.
        # Having the space in it breaks how helioviewer loads images due to
        # the space in the file name. To prevent this problem we're selecting
        # the phrase before the first space only.
        # As an example, "LASCO-C2 Orange" -> "LASCO-C2" which is
        # consistent with our current naming.
        image['nickname'] = imageData.nickname.split(" ")[0]
        # Patch for GONG H-alpha which has a nickname of "NSO-GONG,"
        # Remove the trailing comma
        if image['nickname'].endswith(","): image['nickname'] = image['nickname'][:-1]
        # Remove explicit units from the measurement
        measurement = str(imageData.measurement).replace(".0 Angstrom", "").replace(".0 nm","").replace(".0", "")
        # Convert Yohkoh measurements to be helioviewer compatible
        if image['observatory'] == "Yohkoh":
            if measurement == "AlMg":
                image['measurement'] = "AlMgMn"
            elif measurement == "Al01":
                image['measurement'] = "thin-Al"
            else:
                image['measurement'] = measurement
        elif image['observatory'] == "Hinode":
            image['filter1'] = measurement.split("-")[0].replace(" ", "_")
            image['filter2'] = measurement.split("-")[1].replace(" ", "_")
        elif image['instrument'] == "CCOR1":  # BIG ugly hack because a MAP for CCOR-1 does not exist in map_factory
            image['measurement'] = 'white-light'
        elif image['instrument'] == "CCOR2":
            image['measurement'] = 'white-light'
        else:
            image['measurement'] = measurement
        image['date'] = self._get_date(imageData)
        image['filepath'] = self._filepath
        image['header'] = imageData.meta

        if image["observatory"] == "RHESSI":
            image = self._process_rhessi_extras(image, imageData)

        return image


    def get_observatory(self, imageData):
        """
        Gets the name of the observatory.
        This either returns the observatory that exists in the imageData or
        any overrides we need to apply.
        """
        observatory = imageData.observatory.strip().replace(" ","_")
        if observatory == "CCOR-2":
            return "SWFO-L1"
        if observatory == "CCOR-1":
            return "GOES-19"
        else:
            return observatory


    def get_instrument(self, imageData):
        instrument = imageData.instrument.strip().split(" ")[0]
        return instrument


    def _get_date(self, img: GenericMap) -> Time:
        try:
            return img.date
        except KeyError:
            return Time(img.meta["date_obs"])

    def read_header_only_but_still_use_sunpy_map(self, patch_cunit=False):
        """
        Reads the header for a JPEG200 file and returns some dummy data.
        Why does this function exist?  SunPy map objects perform some important
        homogenization steps that we would like to take advantage of in
        Helioviewer.  The homogenization steps occur on the creation of the sunpy
        map object.  All SunPy maps have the same properties, some of which are
        useful for Helioviewer to use in order to ingest JPEG2000 data.  The SunPy
        map properties are based on the header information in the JPEG2000 file,
        which is a copy of the source FITS header (with some modifications in
        some cases - see JP2Gen).  So by using SunPy's maps, Helioviewer does not
        have to implement these homogenization steps.

        So what's the problem?  Why not use SunPy's JPEG2000 file reading
        capability?  Well let's explain. SunPy's JPEG2000 file reading reads
        both the file header and the image data.  The image data is then decoded
        ultimately creating a numpy array.  The decoding step is computationally
        expensive for the 4k by 4k images provided by AIA and HMI.  It takes long
        enough that the ingestion of AIA and HMI data would be severely impacted,
        possibly to the point that we would never catch up if we fell behind in
        ingesting the latest data.

        The solution is to not decode the image data, but to pass along only the
        minimal amount of information required to create the SunPy map.  This
        function implements this solution tactic, admittedly in an unsatisfying
        manner.  The actual image data is replaced by a 1 by 1 numpy array.  This
        is sufficient to create a SunPy map with the properties required by the
        Helioviewer Project.

        Parameters
        ----------
        filepath : `str`
            The file to be read.

        Returns
        -------
        pairs : `list`
            A (data, header) tuple
        """
        header = self.get_header()
        if (patch_cunit):
            # Patch for SWFO-L1 CCOR-2, CUNIT1 and 2 contain 'degrees'
            if (header[0]['CUNIT1'] == 'degrees' and header[0]['CUNIT2'] == 'degrees'):
                header[0]['CUNIT1'] = 'deg'
                header[0]['CUNIT2'] = 'deg'
            # General patch to get file to be parsed by sunpy
            else:
                # Try fixing the header
                header[0]['cunit1'] = 'arcsec'
                header[0]['cunit2'] = 'arcsec'

        return [(np.zeros([1, 1]), header[0])]


    def get_header(self):
        """
        Reads the header from the file

        Parameters
        ----------
        filepath : `str`
            The file to be read

        Returns
        -------
        headers : list
            A list of headers read from the file
        """
        jp2 = Jp2k(self._filepath)
        xml_box = [box for box in jp2.box if box.box_id == 'xml ']
        xmlstring = ET.tostring(xml_box[0].xml.find('fits'))
        pydict = xml_to_dict(xmlstring)["fits"]

        # Fix types
        for k, v in pydict.items():
            if v.isdigit():
                pydict[k] = int(v)
            elif self._is_float(v):
                pydict[k] = float(v)

        # Remove newlines from comment
        if 'comment' in pydict:
            pydict['comment'] = pydict['comment'].replace("\n", "")

        self._data = pydict

        hv_tag = xml_box[0].xml.find('helioviewer')
        if hv_tag is not None:
            hvxml = ET.tostring(hv_tag)
            self._helioviewer = xml_to_dict(hvxml)["helioviewer"]


        return [FileHeader(pydict)]


    def _is_float(self, s):
        """Check to see if a string value is a valid float"""
        try:
            float(s)
            return True
        except ValueError:
            return False

    def _is_string(self, s):
        # if we use Python 3
        if (sys.version_info[0] >= 3):
            return isinstance(s, str)
        # we use Python 2
        return isinstance(s, basestring)

    def getDSun(self):
        """Returns the distance to the sun in meters
        For images where dsun is not specified it can be determined using:
            dsun = (rsun_1au / rsun_image) * dsun_1au
        """
        maxDSUN = 2.25e11 # A reasonable max for solar observatories ~1.5 AU
        dsun_keys = [
            'DSUN_OBS', # Used by most data sources
            'DSUN'      # Used by RHESSI
        ]
        rsun_keys = [
            'SOLAR_R',  # EIT
            'RADIUS',   # MDI
        ]
        def find_value(data: dict, keys: list[str]) -> Union[float,None]:
            # Find the first instance of any key in data and return its value
            for key in keys:
                if key in data:
                    return data[key]
            return None

        dsun = find_value(self._data, dsun_keys)
        if dsun is None:
            rsun = find_value(self._data, rsun_keys)
            if rsun is not None:
                scale = self._data['CDELT1']
                if scale == 0 :
                    print('JP2 WARNING! Invalid value for CDELT1 (' + self._filepath + '): ' + scale)
                if rsun == 0 :
                    print('JP2 WARNING! Invalid value for RSUN (' + self._filepath + '): ' + rsun)

                dsun = (__HV_CONSTANT_RSUN__ / (rsun * scale)) * __HV_CONSTANT_AU__

        # HMI continuum images may have DSUN = 0.00
        # LASCO/MDI may have rsun=0.00
        if dsun is None:
            dsun = __HV_CONSTANT_AU__

        if dsun <= 0:
            dsun = __HV_CONSTANT_AU__

        # Check to make sure header information is valid
        if self._is_float(dsun) == False or dsun <= 0 or dsun >= maxDSUN:
            print('JP2 WARNING! Invalid value for DSUN (' + self._filepath + '): ' + dsun)

        return dsun

    def getImageDimensions(self):
        """Returns the dimensions for a given image
        @return array JP2 width and height
        """
        ret = dict()

        try:
            ret['width']  = self._data['NAXIS1']
            ret['height'] = self._data['NAXIS2']
        except Exception as e:
            print('JP2 WARNING! Unable to locate image dimensions in header tags! (' + self._filepath + ')')

        return ret


    def getImagePlateScale(self):
        """Returns the plate scale for a given image
        @return string JP2 image scale
        """
        try:
            scale = self._data['CDELT1']
        except Exception as e:
            print( 'JP2 WARNING! Unable to locate image scale in header tags! (' + self._filepath + ')')

        # Check to make sure header information is valid
        if self._is_float(scale) == False or scale <= 0:
            print('JP2 WARNING! Invalid value for CDELT1 (' + self._filepath + '): ' + scale)

        return scale

    def getRefPixelCoords(self):
        """Returns the coordinates for the image's reference pixel.
           NOTE: The values for CRPIX1 and CRPIX2 reflect the x and y coordinates
                 with the origin at the bottom-left corner of the image, not the
                 top-left corner.
           @return array Pixel coordinates of the reference pixel
        """
        ret = dict()

        try:
            if self._data['INSTRUME'] == 'XRT':
                ret['x'] = -(self._data['CRVAL1'] / self._data['CDELT1'] - self._data['CRPIX1'])
                ret['y'] = -(self._data['CRVAL2'] / self._data['CDELT2'] - self._data['CRPIX2'])
            else:
                ret['x'] = self._data['CRPIX1']
                ret['y'] = self._data['CRPIX2']
        except Exception as e:
            print( 'JP2 WARNING! Unable to locate reference pixel coordinates in header tags! (' + self._filepath + ')')

        return ret


    def getSunCenterOffsetParams(self):
        """Returns the Header keywords containing any Sun-center location
           information
           @return array Header keyword/value pairs from JP2 file XML
        """
        sunCenterOffsetParams = dict()

        try:
            if self._data['INSTRUME'] == 'XRT':
                sunCenterOffsetParams['XCEN'] = self._data['XCEN']
                sunCenterOffsetParams['YCEN'] = self._data['YCEN']
                sunCenterOffsetParams['CDELT1'] = self._data['CDELT1']
                sunCenterOffsetParams['CDELT2'] = self._data['CDELT2']
        except Exception as e:
            print('JP2 WARNING! Unable to locate Sun center offset params in header! (' + self._filepath + ')')

        return sunCenterOffsetParams

    def getLayeringOrder(self):
        """Returns layering order based on data source
           NOTE: In the case of Hinode XRT, layering order is decided on an
                 image-by-image basis
           @return integer layering order
        """
        try:
            telescope = self._data['TELESCOP']
            if telescope == 'SOHO':
                layeringOrder = 2     # SOHO LASCO C2
                if self._data['INSTRUME'] == 'EIT':
                    layeringOrder = 1  # SOHO EIT
                elif self._data['INSTRUME'] == 'MDI':
                    layeringOrder = 1  # SOHO MDI
                elif self._data['DETECTOR'] == 'C3':
                    layeringOrder = 3 # SOHO LASCO C3
            elif telescope == 'STEREO':
                layeringOrder = 2     # STEREO_A/B SECCHI COR1
                if self._data['DETECTOR'] == 'COR2':
                    layeringOrder = 3 # STEREO_A/B SECCHI COR2
            elif telescope == 'HINODE':
                layeringOrder = 1     # Hinode XRT full disk
                if self._data['NAXIS1'] * self._data['CDELT1'] < 2048.0 and self._data['NAXIS2'] * self._data['CDELT2'] < 2048.0:
                    layeringOrder = 2 # Hinode XRT sub-field
            else:
                # All other data sources
                layeringOrder = 1
        except Exception as e:
            print('JP2 WARNING! Unable to determine layeringOrder from header tags! (' + self._filepath + ')')

        return layeringOrder

    def getImageRotationStatus(self):
        """Returns true if the image was rotated 180 degrees

           Note that while the image data may have been rotated to make it easier
           to line up different data sources, the meta-information regarding the
           sun center, etc. are not adjusted, and thus must be manually adjusted
           to account for any rotation.

           @return boolean True if the image has been rotated
        """
        try:
            rotation = self._data['CROTA1']
            if abs(rotation) > 170:
                return true
        except Exception as e:
            # AIA, EIT, and MDI do their own rotation
            return False

    def _process_rhessi_extras(self, image: dict, imageData: GenericMap) -> dict:
        """
        Performs extra processing specific to RHESSI images.
        This extracts the energy band and reconstruction methods into the img dict.
        """
        image[
            "energy_band"
        ] = f"{imageData.meta['energy_l']}_{imageData.meta['energy_h']}"
        image["reconstruction_method"] = self._helioviewer[
            "HV_RHESSI_IMAGE_RECONSTRUCTION_METHOD"
        ]
        return image

