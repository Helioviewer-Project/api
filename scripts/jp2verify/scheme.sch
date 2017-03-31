<?xml version="1.0" encoding="UTF-8"?>
<!--
    HelioViewer File Format Verification Facility (HVFFVF)
    v0.2 preliminary

    $Id$
-->
<iso:schema xmlns="http://purl.oclc.org/dsdl/schematron" xmlns:iso="http://purl.oclc.org/dsdl/schematron" queryBinding="xslt" schemaVersion="iso">
    <iso:title>Check for HV JP2 compliance</iso:title>
    <iso:pattern>
        <!-- checks on jpylyzer output -->
        <!-- check presence of jpylyzer element -->
        <iso:rule context="/">
            <iso:assert test="jpylyzer">no jpylyzer element found</iso:assert>
        </iso:rule>
        <!-- check presence of isValidJP2 element with the text 'True' -->
        <!--<iso:rule context="/jpylyzer">
            <iso:assert test="isValidJP2 = 'True'">not valid JP2</iso:assert>
        </iso:rule>-->
        <!-- check jpylyzer validation of xmlBox if there -->
        <!--<iso:rule context="/jpylyzer/tests/xmlBox">
            <iso:assert test="containsWellformedXML != 'False'">malformed XML metadata</iso:assert>
        </iso:rule>-->
        <!-- checks presence and structure of xmlBox element -->
        <!-- context="/jpylyzer" -->
        <!--<iso:rule context="properties">
            <iso:assert test="xmlBox">no XML box</iso:assert>
        </iso:rule>-->
        <!--<iso:rule context="properties/xmlBox">
            <iso:assert test="meta">meta missing</iso:assert>
            <iso:assert test="meta/fits">meta/fits missing</iso:assert>
            <iso:assert test="meta/helioviewer">meta/helioviewer missing</iso:assert>
        </iso:rule>-->
        <!-- checks on XML metadata -->
        <!-- context="/jpylyzer/properties/xmlBox/meta" -->
        <!-- old style -->
        <iso:rule context="fits">
            <iso:assert test="TELESCOP">keyword missing: TELESCOP</iso:assert>
            <iso:assert test="INSTRUME">keyword missing: INSTRUME</iso:assert>
            <iso:assert test="WAVELNTH">keyword missing: WAVELNTH</iso:assert>
            <iso:assert test="DATE-OBS">keyword missing: DATE-OBS</iso:assert>
            <iso:assert test="DSUN_OBS">keyword missing: DSUN_OBS</iso:assert>
            <iso:assert test="CDELT1">keyword missing: CDELT1</iso:assert>
            <iso:assert test="CDELT2">keyword missing: CDELT2</iso:assert>
            <iso:assert test="CRPIX1">keyword missing: CRPIX1</iso:assert>
            <iso:assert test="CRPIX2">keyword missing: CRPIX2</iso:assert>
        </iso:rule>
        <!-- filename check -->
        <iso:rule context="fits">
            <iso:let name="date-obs" value="replace(translate(substring-before(DATE-OBS, '.'), '-:', '__'), 'T', '__')"/>
            <iso:let name="telescop" value="replace(TELESCOP, '/', '_')"/>
            <iso:let name="invalid-detector" value="INSTRUME = 'SWAP' or not(DETECTOR)"/>
            <iso:let name="detector" value="concat(substring(INSTRUME, 1, invalid-detector * string-length(INSTRUME)), substring(DETECTOR, 1, not(invalid-detector) * string-length(DETECTOR)))"/>
            <iso:let name="filename" value="concat($date-obs, '_', $telescop, '_', INSTRUME, '_', $detector, '_', WAVELNTH, '.jp2')"/>
            <iso:assert test="$filename = /jpylyzer/fileInfo/fileName">invalid filename</iso:assert>
        </iso:rule>
        <!-- checks on codestream parameters -->
        <!-- context="/jpylyzer/properties/contiguousCodestreamBox" -->
        <!-- SIZ -->
        <iso:rule context="siz">
            <iso:assert test="numberOfTiles = 1">tiled image</iso:assert>
        </iso:rule>
        <!-- COD -->
        <!--<iso:rule context="cod">
            <iso:assert test="precincts = 'yes'">no precincts</iso:assert>
            <iso:assert test="precincts != 'yes' or precinctSizeX &gt; 127">invalid precinct X size</iso:assert>
            <iso:assert test="precincts != 'yes' or precinctSizeY &gt; 127">invalid precinct Y size</iso:assert>
            <iso:assert test="order = 'RPCL'">wrong progression order</iso:assert>
        </iso:rule>-->
        <!-- tiles -->
        <iso:rule context="tileParts">
            <!-- PLT markers -->
            <iso:assert test="tilePart/plt">missing PLT markers</iso:assert>
        </iso:rule>
    </iso:pattern>
</iso:schema>
