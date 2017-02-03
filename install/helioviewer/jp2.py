# -*- coding: utf-8 -*-
"""Helioviewer.org JPEG 2000 processing functions"""
import os
from xml.etree import cElementTree as ET
import numpy as np
from sunpy.io.jp2 import get_header
from sunpy.map import Map
from sunpy.util.xml import xml_to_dict
from sunpy.io.header import FileHeader
from glymur import Jp2k
from helioviewer.db import get_datasources, enable_datasource


__INSERTS_PER_QUERY__ = 500
__STEP_FXN_THROTTLE__ = 50


def read_header_only_but_still_use_sunpy_map(filepath):
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
    header = get_header(filepath)

    return [(np.zeros([1, 1]), header[0])]


def get_header(filepath):
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
    jp2 = Jp2k(filepath)
    xml_box = [box for box in jp2.box if box.box_id == 'xml ']
    xmlstring = ET.tostring(xml_box[0].xml.find('fits'))
    pydict = xml_to_dict(xmlstring)["fits"]

    # Fix types
    for k, v in pydict.items():
        if v.isdigit():
            pydict[k] = int(v)
        elif _is_float(v):
            pydict[k] = float(v)

    # Remove newlines from comment
    if 'comment' in pydict:
        pydict['comment'] = pydict['comment'].replace("\n", "")

    return [FileHeader(pydict)]


def _is_float(s):
    """Check to see if a string value is a valid float"""
    try:
        float(s)
        return True
    except ValueError:
        return False


def create_image_data(filepath):
    """Create data object of JPEG 2000 image.

    Get image observatory, instrument, detector, measurement, date from image
    metadata and create an object.
    """
    imageData = Map(read_header_only_but_still_use_sunpy_map(filepath))
    image = dict()
    image['nickname'] = imageData.nickname
    image['observatory'] = imageData.observatory.replace(" ","_")
    image['instrument'] = imageData.instrument.split(" ")[0]
    image['detector'] = imageData.detector
    measurement = str(imageData.measurement).replace(".0 Angstrom", "").replace(".0", "")
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
    else:
        image['measurement'] = measurement
    image['date'] = imageData.date
    image['filepath'] = filepath
    image['header'] = imageData.meta

    return image


def find_images(path):
    """Searches a directory for JPEG 2000 images.

    Traverses file-tree starting with the specified path and builds a list of
    the available images.
    """
    images = []

    for root, dirs, files in os.walk(path):
        for file_ in files:
            if file_.endswith('.jp2'):
                images.append(os.path.join(root, file_))

    return images


def process_jp2_images(images, root_dir, cursor, mysql=True, step_fxn=None, cursor_v2=None):
    """Processes a collection of JPEG 2000 Images"""
    #if mysql:
    #    import mysql.connector
    #else:
    #    import pgdb

    # Return tree of known data-sources
    sources = get_datasources(cursor)

    # Insert images into database, 500 at a time
    while len(images) > 0:
        subset = images[:__INSERTS_PER_QUERY__]
        images = images[__INSERTS_PER_QUERY__:]
        insert_images(subset, sources, root_dir, cursor, mysql, step_fxn, cursor_v2)


def insert_images(images, sources, rootdir, cursor, mysql, step_function=None, cursor_v2=None):
    """Inserts multiple images into a database using a single query

    Parameters
    ----------
    images : list
        list of image dict representations
    sources : list
        tree of datasources supported by Helioviewer
    rootdir : string
        image archive root directory
    cursor : mixed
        database cursor
    mysql : bool
        whether or not MySQL syntax should be used
    step_function : function
        function to call after each insert query
    """
    
    # TEMPORARY SOLUTION
    # Because of database tables changes in Helioviewer v2 and Helioviewer v3
    # we need have same data on both databases we need to insert image information into both databases
    # separatly.
    #
    # To solve this we duplicated query with different table names and exetuning it to different databases.
    #
    query = "INSERT IGNORE INTO data VALUES "
    query_v2 = "INSERT IGNORE INTO images VALUES "

    # Add images to SQL query
    for i, img in enumerate(images):
        # break up directory and filepath
        directory, filename = os.path.split(img['filepath'])

        path = "/" + os.path.relpath(directory, rootdir)

        prev = ""
        source = sources
        
        if img['observatory'] == "Hinode":
            leafs = ["observatory", "instrument", "detector", "filter1", "filter2"]
        else:
            leafs = ["observatory", "instrument", "detector", "measurement"]
            
        for leaf in leafs:

            if img[leaf] != prev:
                source = source[str(img[leaf])]
            prev = img[leaf]

        # Enable datasource if it has not already been
        if (not source['enabled']):
            # sources[img["observatory"]][img["instrument"]][img["detector"]][img["measurement"]]["enabled"] = True
            enable_datasource(cursor, source['id'])

        # insert into database
        query += "(NULL, '%s', '%s', '%s', NULL, %d, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 0)," % (path, filename, img["date"], source['id'])
        query_v2 += "(NULL, '%s', '%s', '%s', %d)," % (path, filename, img["date"], source['id'])

        # Progressbar
        if step_function and (i + 1) % __STEP_FXN_THROTTLE__ is 0:
            step_function(filename)

    # Remove trailing comma
    query = query[:-1] + ";"
    query_v2 = query_v2[:-1] + ";"

    # Execute query
    try:
        cursor.execute(query)
    except Exception as e:
        print("Error: " + e.args[1])
    
    if cursor_v2:
        try:
            cursor_v2.execute(query_v2)
        except Exception as e:
            print("Error: " + e.args[1])


class BadImage(ValueError):
    """Exception to raise when a "bad" image (e.g. corrupt or calibration) is
    encountered."""
    def __init__(self, message=""):
        self.message = message

    def get_message(self):
        return self.message
