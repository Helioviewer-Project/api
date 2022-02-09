# -*- coding: utf-8 -*-
"""Helioviewer.org JPEG 2000 processing functions"""
import os
import sys
from helioviewer.db import get_datasources, enable_datasource
from helioviewer.jp2parser import JP2parser

__INSERTS_PER_QUERY__ = 500
__STEP_FXN_THROTTLE__ = 50

def create_image_data(filepath):
    """Create data object of JPEG 2000 image.

    Get image observatory, instrument, detector, measurement, date from image
    metadata and create an object.
    """
    JP2data = JP2parser(filepath)
    image = JP2data.getData()

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


def process_jp2_images(images, root_dir, db, cursor, mysql=True, step_fxn=None, cursor_v2=None):
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
        insert_images(subset, sources, root_dir, db, cursor, mysql, step_fxn, cursor_v2)


def insert_images(images, sources, rootdir, db, cursor, mysql, step_function=None, cursor_v2=None):
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
            enable_datasource(cursor, source['id'])

        groups = getImageGroup(source['id'])

        # insert into database
        queryStr = "(NULL, '%s', '%s', '%s', NULL, %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', 1, %d, %d, %d),"

        query += queryStr % (path, filename, img["date"], source['id'],
                  img["scale"], img["width"], img["height"], img["refPixelX"], img["refPixelY"], img["layeringOrder"],
                  img["DSUN_OBS"], img["SOLAR_R"], img["RADIUS"], img["CDELT1"], img["CDELT2"],
                  img["CRVAL1"], img["CRVAL2"], img["CRPIX1"], img["CRPIX2"], img["XCEN"], img["YCEN"],
                  img["CROTA1"], groups["groupOne"], groups["groupTwo"], groups["groupThree"])

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
	# Commit enabling datasources
        db.commit()
    except Exception as e:
        print("Error: " + e.args[1])

    if cursor_v2:
        try:
            cursor_v2.execute(query_v2)
            # Commit enabling datasources
            db.commit()
        except Exception as e:
            print("Error: " + e.args[1])


class BadImage(ValueError):
    """Exception to raise when a "bad" image (e.g. corrupt or calibration) is
    encountered."""
    def __init__(self, message=""):
        self.message = message

    def get_message(self):
        return self.message


def getImageGroup(sourceId):
    """Create data object of XRT groups

    TODO: move groupId definition to the database
    """
    groups = dict()
    groups["groupOne"] = 0
    groups["groupTwo"] = 0
    groups["groupThree"] = 0

    if sourceId > 37 and sourceId < 75:
        groups["groupOne"] = 10001

        # ID 68 messes up the math because it doesn't fit the pattern.
        # So for values > 68 offset it by 1 so we basically ignore it
        # in the math. This is confirmed to work via test_jp2.py
        offset = sourceId
        if sourceId > 68:
            offset = sourceId - 1

        # sourceId 68 doesn't follow the pattern. groups 2 and 3 should
        # be 0 for 68, so exclude it here.
        if sourceId != 68:
            # thanks to the pattern of how the data is laid out
            # group 2 follows a modulus of 10002 - 10007 and repeats
            # i.e. 38 -> 10002 ... 43 -> 10007, 44 -> 10002 ... 49 -> 10007, etc
            # So group 2 can be set with a modulus
            groups["groupTwo"] = ((offset - 38) % 6) + 10002

            # Group 3 has a similar pattern, every 6 share a group and then
            # the group is incremented by one.
            # i.e. 38 -> 10008 ... 43 -> 10008, 44 -> 10009 ... 49 -> 10009, etc
            # Count on integer division to drop the result in the correct bucket
            # 38 to 43 when subtracted will return 0 - 5, when divided by 6 returns 0.
            # 44 to 49 when subtracted will return 6 - 11, when divided by 6 returns 1.
            # etc.
            groups["groupThree"] = ((offset - 38) // 6) + 10008

    return groups
