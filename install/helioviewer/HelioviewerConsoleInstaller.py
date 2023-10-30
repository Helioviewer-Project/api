# -*- coding: utf-8 -*-
"""A text-based installer for Helioviewer.org"""
import sys
import os
import subprocess
import math
import getpass
from datetime import datetime
from org.helioviewer.jp2 import *
from org.helioviewer.db  import *

class HelioviewerConsoleInstaller:
    """Text-based installer class"""
    def __init__(self, options):
        self.options = options

    def getFilePath(self):
        '''Prompts the user for the directory information'''

        path = get_input("Location of JP2 Images: ")
        while not os.path.isdir(path):
            print("That is not a valid directory! Please try again.")
            path = get_input("Location of JP2 Images: ")

        return path

    def getDatabaseType(self):
        ''' Prompts the user for the database type '''
        dbtypes = {1: "mysql", 2: "postgres"}

        while True:
            print("Please select the desired database to use for installation:")
            print("   [1] MySQL")
            print("   [2] PostgreSQL")
            choice = get_input("Choice: ")

            if choice not in ['1', '2']:
                print("Sorry, that is not a valid choice.")
            else:
                return dbtypes[int(choice)]

    def getDatabasename(self):
        ''' Prompts the user for the database name '''

        dbname = get_input("    Database name [helioviewer]: ")

        # Default values
        if not dbname: dbname = "helioviewer"

        return dbname

    def shouldSetupSchema(self):
        ''' Prompts the user for the database type '''
        options = {1: True, 2: False}

        while True:
            print("Would you like to create the database schema used by "
                  "Helioviewer.org?:")
            print("   [1] Yes")
            print("   [2] No")
            choice = get_input("Choice: ")

            if choice not in ['1', '2']:
                print("Sorry, that is not a valid choice.")
            else:
                return options[int(choice)]

    def getDatabaseInfo(self):
        ''' Gets database type and administrator login information '''
        import getpass

        while True:
            dbtype = self.getDatabaseType()
            dbuser = get_input("    Username: ")
            dbpass = getpass.getpass("    Password: ")

            # Default values
            if not dbuser:
                dbuser = "root"

            # MySQL?
            mysql = dbtype is "mysql"

            return dbuser,dbpass,mysql

    def getNewUserInfo(self):
        ''' Prompts the user for the required database information '''

        # Get new user information (Todo 2009/08/24: validate input form)
        dbuser = get_input("    Username [helioviewer]: ")
        dbpass = get_input("    Password [helioviewer]: ")

        # Default values
        if not dbuser:
            dbuser = "helioviewer"
        if not dbpass:
            dbpass = "helioviewer"

        return dbuser, dbpass

    def printGreeting(self):
        ''' Prints a greeting to the user'''
        subprocess.call("clear", shell=True)

        print("""\
====================================================================
= Helioviewer Database Population Script                           =
= Last updated: 2010/10/07                                         =
=                                                                  =
= This script processes JP2 images, extracts their associated      =
= meta-information and stores it away in a database.               =
=                                                                  =
====================================================================""")

def loadTextInstaller(options):
    ''' Loads the text-based installation tool '''
    app = HelioviewerConsoleInstaller(options)
    app.printGreeting()

    # Filepath
    path = app.getFilePath()

    # Locate jp2 images in specified filepath
    images = traverse_directory(path)

    # Check to make sure the filepath contains jp2 images
    if len(images) is 0:
        print("No JPEG 2000 images found. Exiting installation.")
        sys.exit(2)
    else:
        print("Found %d JPEG2000 images." % len(images))

    # Setup database schema if needed
    if (app.shouldSetupSchema()):
        print("Please enter new database information:")
        dbname = app.getDatabasename()
        hvuser, hvpass = app.getNewUserInfo()

        print("")

        # Get database information
        print("Please enter existing database admin information:")
        dbuser, dbpass, mysql = app.getDatabaseInfo()

        # Setup database schema
        db, cursor = setup_database_schema(hvuser, hvpass, "localhost", dbname, dbuser, dbpass, mysql)

    else:
        # Get database information
        print("Please enter Helioviewer.org database name")
        dbname = app.getDatabasename()

        print("Please enter Helioviewer.org database user information")
        dbuser, dbpass, mysql = app.getDatabaseInfo()
        cursor = get_db_cursor("localhost", dbname, dbuser, dbpass, mysql)

    print("Processing Images...")

    # Insert image information into database
    process_jp2_images(images, path, db, cursor, mysql)
    cursor.close()

    print("Finished!")

def loadUpdater(options):
    ''' Loads the text-based installation tool and runs it in update-mode '''
    app = HelioviewerConsoleInstaller(options)

    # MySQL?
    mysql = options.dbtype == "mysql"

    db, cursor = get_db_cursor("localhost", options.dbname, options.dbuser, options.dbpass, mysql)

    print("Processing Images...")

    # Insert image information into database
    process_jp2_images(options.files, options.basedir, db, cursor, mysql)

    cursor.close()

    print("Finished!")

# raw_input
if sys.version_info[0] >= 3:
    get_input = input
else:
    get_input = raw_input

