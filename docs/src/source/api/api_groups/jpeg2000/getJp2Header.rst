getJP2Header
^^^^^^^^^^^^
GET /v2/getJP2Header/

Get the XML header embedded in a JPEG2000 image. Includes the FITS header as
well as a section of Helioviewer-specific metadata.

.. table:: `Request Parameters:`

    +-----------+----------+---------+----------------------+---------------------------------------------------------------+
    | Parameter | Required | Type    | Example              | Description                                                   |
    +===========+==========+=========+======================+===============================================================+
    | id        | Required | number  | 7654321              | Unique JP2 image identifier.                                  |
    +-----------+----------+---------+----------------------+---------------------------------------------------------------+
    | callback  | Optional | string  |    a                 | Wrap the response object in a function call of your choosing. |
    +-----------+----------+---------+----------------------+---------------------------------------------------------------+

Example: string (XML)
~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getJP2Header/?id=7654321

.. code-block::
    :caption: Example Response:

    <?xml version="1.0" encoding="utf-8"?>
    <meta>
        <fits>
            <SIMPLE>1</SIMPLE>
            <BITPIX>16</BITPIX>
            <NAXIS>2</NAXIS>
            <NAXIS1>4096</NAXIS1>
            <NAXIS2>4096</NAXIS2>
            <EXTEND>1</EXTEND>
            <DATE_OBS>2011-05-25T09:07:00.34</DATE_OBS>
            <ORIGIN>SDO</ORIGIN>
            <DATE>2011-05-25T09:15:44</DATE>
            <TELESCOP>SDO</TELESCOP>
            <INSTRUME>AIA_3</INSTRUME>
            <DATE-OBS>2011-05-25T09:07:00.34</DATE-OBS>
            <T_OBS>2011-05-25T09:07:01.34Z</T_OBS>
            <TOBSSTEP>1.0000000</TOBSSTEP>
            <TOBSEPOC>1977.01.01_00:00:00_TAI</TOBSEPOC>
            <CAMERA>3</CAMERA>
            <IMG_TYPE>LIGHT</IMG_TYPE>
            <EXPTIME>2.0002010</EXPTIME>
            <EXPSDEV>0.00012300000</EXPSDEV>
            <INT_TIME>2.2734380</INT_TIME>
            <WAVELNTH>171</WAVELNTH>
            <WAVEUNIT>angstrom</WAVEUNIT>
            <WAVE_STR>171_THIN</WAVE_STR>
            <FSN>26454194</FSN>
            <FID>0</FID>
            <LVL_NUM>1.5000000</LVL_NUM>
            <QUALLEV0>0</QUALLEV0>
            <QUALITY>1073741824</QUALITY>
            <TOTVALS>16777216</TOTVALS>
            <DATAVALS>16777216</DATAVALS>
            <MISSVALS>0</MISSVALS>
            <PERCENTD>100.000</PERCENTD>
            <DATAMIN>0</DATAMIN>
            <DATAMAX>5041</DATAMAX>
            <DATAMEDN>143</DATAMEDN>
            <DATAMEAN>197.450</DATAMEAN>
            <DATARMS>240.880</DATARMS>
            <DATASKEW>3.37000</DATASKEW>
            <DATAKURT>-18.5000</DATAKURT>
            <OSCNMEAN>nan</OSCNMEAN>
            <OSCNRMS>nan</OSCNRMS>
            <FLAT_REC>aia.flatfield[:#30]</FLAT_REC>
            <CTYPE1>HPLN-TAN</CTYPE1>
            <CUNIT1>arcsec</CUNIT1>
            <CRVAL1>0.0000000</CRVAL1>
            <CDELT1>0.60000000</CDELT1>
            <CRPIX1>2048.5000</CRPIX1>
            <CTYPE2>HPLT-TAN</CTYPE2>
            <CUNIT2>arcsec</CUNIT2>
            <CRVAL2>0.0000000</CRVAL2>
            <CDELT2>0.60000000</CDELT2>
            <CRPIX2>2048.5000</CRPIX2>
            <CROTA2>0.0000000</CROTA2>
            <R_SUN>1581.4006</R_SUN>
            <MPO_REC>sdo.master_pointing[:#158]</MPO_REC>
            <INST_ROT>0.10248800</INST_ROT>
            <IMSCL_MP>0.59907600</IMSCL_MP>
            <X0_MP>2049.9199</X0_MP>
            <Y0_MP>2048.8101</Y0_MP>
            <RSUN_LF>nan</RSUN_LF>
            <X0_LF>nan</X0_LF>
            <Y0_LF>nan</Y0_LF>
            <ASD_REC>sdo.lev0_asd_0004[:#10393635]</ASD_REC>
            <SAT_Y0>-4.9394650</SAT_Y0>
            <SAT_Z0>8.3641070</SAT_Z0>
            <SAT_ROT>2.6000000e-05</SAT_ROT>
            <ACS_MODE>SCIENCE</ACS_MODE>
            <ACS_ECLP>NO</ACS_ECLP>
            <ACS_SUNP>YES</ACS_SUNP>
            <ACS_SAFE>NO</ACS_SAFE>
            <ACS_CGT>GT3</ACS_CGT>
            <ORB_REC>sdo.fds_orbit_vectors[2011.05.25_09:07:00_UTC]</ORB_REC>
            <DSUN_REF>1.4959787e+11</DSUN_REF>
            <DSUN_OBS>1.5153469e+11</DSUN_OBS>
            <RSUN_REF>6.9600000e+08</RSUN_REF>
            <RSUN_OBS>947.37917</RSUN_OBS>
            <GCIEC_X>nan</GCIEC_X>
            <GCIEC_Y>nan</GCIEC_Y>
            <GCIEC_Z>nan</GCIEC_Z>
            <HCIEC_X>nan</HCIEC_X>
            <HCIEC_Y>nan</HCIEC_Y>
            <HCIEC_Z>nan</HCIEC_Z>
            <OBS_VR>-1256.5236</OBS_VR>
            <OBS_VW>31812.788</OBS_VW>
            <OBS_VN>3667.9192</OBS_VN>
            <CRLN_OBS>151.31955</CRLN_OBS>
            <CRLT_OBS>-1.4993120</CRLT_OBS>
            <CAR_ROT>2110</CAR_ROT>
            <ROI_NWIN>-2147483648</ROI_NWIN>
            <ROI_SUM>-2147483648</ROI_SUM>
            <ROI_NAX1>-2147483648</ROI_NAX1>
            <ROI_NAY1>-2147483648</ROI_NAY1>
            <ROI_LLX1>-2147483648</ROI_LLX1>
            <ROI_LLY1>-2147483648</ROI_LLY1>
            <ROI_NAX2>-2147483648</ROI_NAX2>
            <ROI_NAY2>-2147483648</ROI_NAY2>
            <ROI_LLX2>-2147483648</ROI_LLX2>
            <ROI_LLY2>-2147483648</ROI_LLY2>
            <ISPSNAME>aia.lev0_isp_0011</ISPSNAME>
            <ISPPKTIM>2011-05-25T09:06:57.50Z</ISPPKTIM>
            <ISPPKTVN>001.197</ISPPKTVN>
            <AIVNMST>453</AIVNMST>
            <AIMGOTS>1685005655</AIMGOTS>
            <ASQHDR>2.1739378e+09</ASQHDR>
            <ASQTNUM>2</ASQTNUM>
            <ASQFSN>26454194</ASQFSN>
            <AIAHFSN>26454186</AIAHFSN>
            <AECDELAY>1535</AECDELAY>
            <AIAECTI>0</AIAECTI>
            <AIASEN>0</AIASEN>
            <AIFDBID>241</AIFDBID>
            <AIMGOTSS>5382</AIMGOTSS>
            <AIFCPS>10</AIFCPS>
            <AIFTSWTH>0</AIFTSWTH>
            <AIFRMLID>3025</AIFRMLID>
            <AIFTSID>40961</AIFTSID>
            <AIHISMXB>7</AIHISMXB>
            <AIHIS192>8386460</AIHIS192>
            <AIHIS348>8388608</AIHIS348>
            <AIHIS604>8388608</AIHIS604>
            <AIHIS860>8388608</AIHIS860>
            <AIFWEN>204</AIFWEN>
            <AIMGSHCE>2000</AIMGSHCE>
            <AECTYPE>2</AECTYPE>
            <AECMODE>ON</AECMODE>
            <AISTATE>CLOSED</AISTATE>
            <AIAECENF>1</AIAECENF>
            <AIFILTYP>0</AIFILTYP>
            <AIMSHOBC>54.787998</AIMSHOBC>
            <AIMSHOBE>68.779999</AIMSHOBE>
            <AIMSHOTC>40.528000</AIMSHOTC>
            <AIMSHOTE>25.516001</AIMSHOTE>
            <AIMSHCBC>2054.9199</AIMSHCBC>
            <AIMSHCBE>2068.8201</AIMSHCBE>
            <AIMSHCTC>2040.8040</AIMSHCTC>
            <AIMSHCTE>2025.8719</AIMSHCTE>
            <AICFGDL1>0</AICFGDL1>
            <AICFGDL2>137</AICFGDL2>
            <AICFGDL3>201</AICFGDL3>
            <AICFGDL4>236</AICFGDL4>
            <AIFOENFL>1</AIFOENFL>
            <AIMGFSN>5</AIMGFSN>
            <AIMGTYP>0</AIMGTYP>
            <AIAWVLEN>7</AIAWVLEN>
            <AIAGP1>0</AIAGP1>
            <AIAGP2>0</AIAGP2>
            <AIAGP3>0</AIAGP3>
            <AIAGP4>0</AIAGP4>
            <AIAGP5>0</AIAGP5>
            <AIAGP6>0</AIAGP6>
            <AIAGP7>0</AIAGP7>
            <AIAGP8>393</AIAGP8>
            <AIAGP9>457</AIAGP9>
            <AIAGP10>748</AIAGP10>
            <AGT1SVY>3</AGT1SVY>
            <AGT1SVZ>-7</AGT1SVZ>
            <AGT2SVY>2</AGT2SVY>
            <AGT2SVZ>-3</AGT2SVZ>
            <AGT3SVY>0</AGT3SVY>
            <AGT3SVZ>0</AGT3SVZ>
            <AGT4SVY>-2</AGT4SVY>
            <AGT4SVZ>2</AGT4SVZ>
            <AIMGSHEN>13</AIMGSHEN>
            <RECNUM>20306521</RECNUM>
            <BLANK>-32768</BLANK>
            <CHECKSUM>CAWfE5VZCAVdC3VZ</CHECKSUM>
            <DATASUM>368618671</DATASUM>
            <XCEN>0.00000</XCEN>
            <YCEN>0.00000</YCEN>
            <history>FITSHEAD2STRUCT run at: Wed May 25 02:27:23 2011
    </history>
            <comment>FITS (Flexible Image Transport System) format is defined in 'Astronomy
    and Astrophysics', volume 376, page 359; bibcode: 2001A&amp;A...376..359H
    FITSHEAD2STRUCT
    </comment>
        </fits>
        <helioviewer>
            <HV_ROTATION>0.00000</HV_ROTATION>
            <HV_JP2GEN_VERSION>0.8</HV_JP2GEN_VERSION>
            <HV_JP2GEN_BRANCH_REVISION>No valid revision number found. Bazaar not installed? Using HV_WRITTENBY manually included revision number: 84 [2011/01/10, https://launchpad.net/jp2gen] : % SPAWN: Error managing child process.:  No such file or directory</HV_JP2GEN_BRANCH_REVISION>
            <HV_HVS_DETAILS_FILENAME>hvs_version5.pro</HV_HVS_DETAILS_FILENAME>
            <HV_HVS_DETAILS_FILENAME_VERSION>5.0</HV_HVS_DETAILS_FILENAME_VERSION>
            <HV_COMMENT>JP2 file created locally at Lockheed LMSAL using hv_aia_list2jp2_gs2 at Wed May 25 02:27:24 2011.
    Contact Helioviewer LMSAL Franchise (slater@lmsal.com) for more details/questions/comments regarding this JP2 file.
    HVS (Helioviewer setup) file used to create this JP2 file: hvs_version5.pro (version 5.0).
    FITS to JP2 source code provided by ESA/NASA Helioviewer Project [contact the Helioviewer Project at webmaster@helioviewer.org][NASA-GSFC] and is available for download at https://launchpad.net/jp2gen.
    Please contact the source code providers if you suspect an error in the source code.
    Full source code for the entire Helioviewer Project can be found at https://github.com/Helioviewer-Project.</HV_COMMENT>
            <HV_SUPPORTED>TRUE</HV_SUPPORTED>
        </helioviewer>
    </meta>
