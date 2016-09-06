# -*- coding: utf-8 -*-
"""Helioviewer.org JPEG 2000 processing functions"""
import os
import logging
import sunpy
from sunpy.io.header import FileHeader
from sunpy.io.jp2 import get_header
from sunpy.map import Map
from helioviewer.db import get_datasources, enable_datasource
import traceback
import sys

__INSERTS_PER_QUERY__ = 500
__STEP_FXN_THROTTLE__ = 50

def create_image_data(filepath):
    '''Create data object of JPEG 2000 image.

    Get image observatory, instrument, detector, measurement, date from image metadata
    and create an object
    '''
    imageData = Map(filepath)
    image = {}
    image['nickname'] = imageData.nickname
    image['observatory'] = imageData.observatory.replace(" ","_")
    image['instrument'] = imageData.instrument.split(" ")[0]
    image['detector'] = imageData.detector
    measurement = str(imageData.measurement).replace(".0 Angstrom","").replace(".0","")
    #Convert Yohkoh measurements to helioviewer compatible
    if image['observatory'] == "Yohkoh":
        if measurement == "AlMg":
            image['measurement'] = "AlMgMn"
        elif measurement == "Al01":
            image['measurement'] = "thin-Al"
        else:
            image['measurement'] = measurement
    else:
        image['measurement'] = measurement
    image['date'] = imageData.date
    image['filepath'] = filepath

    return image
    
def find_images(path):
    '''Searches a directory for JPEG 2000 images.

    Traverses file-tree starting with the specified path and builds a list of
    the available images.
    '''
    images = []

    for root, dirs, files in os.walk(path):
        for file_ in files:
            if file_.endswith('.jp2'):
                images.append(os.path.join(root, file_))

    return images

def process_jp2_images (images, root_dir, cursor, mysql=True, step_fxn=None, cursor_v2=None):
    '''Processes a collection of JPEG 2000 Images'''
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
        for leaf in ["observatory", "instrument", "detector", "measurement"]:

            if img[leaf] != prev:
                source = source[str(img[leaf])]
            prev = img[leaf]

        # Enable datasource if it has not already been
        if (not source['enabled']):
            # sources[img["observatory"]][img["instrument"]][img["detector"]][img["measurement"]]["enabled"] = True
            enable_datasource(cursor, source['id'])

        # insert into database
        query += "(NULL, '%s', '%s', '%s', NULL, %d)," % (path, filename, img["date"], source['id'])
        query_v2 += "(NULL, '%s', '%s', '%s', %d)," % (path, filename, img["date"], source['id'])

        # Progressbar
        if step_function and (i + 1) % __STEP_FXN_THROTTLE__ is 0:
            step_function(filename)

    # Remove trailing comma
    query = query[:-1] + ";"
    query_v2 = query_v2[:-1] + ";"

    # Execute query
    cursor.execute(query)
    
    if cursor_v2:
    	cursor_v2.execute(query_v2)
    	

class BadImage(ValueError):
    """Exception to raise when a "bad" image (e.g. corrupt or calibration) is
    encountered."""
    def __init__(self, message=""):
        self.message = message
    def get_message(self):
        return self.message
