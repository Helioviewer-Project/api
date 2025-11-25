# -*- coding: utf-8 -*-
"""A text-based installer for Helioviewer.org"""
import sys
import os
import subprocess
import gc
import getpass
from helioviewer.jp2 import *
from helioviewer.db  import *

class HelioviewerConsoleInstaller:
    """Text-based installer class"""
    def __init__(self):
        self.print_greeting()

        path = self.get_filepath()

        # Locate jp2 images in specified filepath
        filepaths = find_images(path)

        # Setup database schema if needed
        db, cursor, mysql = self.get_db_cursor()

        print("Processing Images...")

        # Extract image parameters, 10,000 at a time
        while len(filepaths) > 0:
            subset = filepaths[:10000]
            filepaths = filepaths[10000:]

            images = []

            for filepath in subset:
                try:
                    image = create_image_data(filepath)
                    images.append(image)
                except:
                    #raise BadImage("HEADER")
                    print("Skipping corrupt image: %s" % os.path.basename(filepath))
                    print(sys.exc_info())
                    continue

            # Insert image information into database
            if len(images) > 0:
                process_jp2_images(images, path, db, cursor, mysql)

            # clean up afterwards
            images = []
            gc.collect()

        # close db connection
        cursor.close()

        print("Finished!")

    def get_db_cursor(self):
        """Returns a database cursor"""
        if (self.should_setup_db()):
            return self.create_db()
        else:
            return self.get_existing_db_info()

    def get_existing_db_info(self):
        """Gets information about existing database from user and returns
        a cursor to that database."""

        print("Please enter Helioviewer.org database host")
        dbhost = self.get_database_host()

        print("Please enter Helioviewer.org database name")
        dbname = self.get_database_name()

        print("Please enter Helioviewer.org database user information")
        dbuser, dbpass, mysql = self.get_database_info()

        db, cursor = get_db_cursor(dbhost, dbname, dbuser, dbpass, mysql)

        return db, cursor, mysql

    def create_db(self):
        """Sets up the database tables needed for Helioviewer"""
        print("Please enter new database information:")
        dbname = self.get_database_name()
        hvuser, hvpass = self.get_new_user_info()

        # Get database information
        print("\nPlease enter existing database admin information:")
        dbhost = self.get_database_host()
        dbuser, dbpass, mysql = self.get_database_info()

        # Setup database schema
        try:
            db, cursor = setup_database_schema(dbuser, dbpass, dbhost, dbname, hvuser, hvpass, mysql)
            return db, cursor, mysql
        except OSError as err:
            print("OS error: {0}".format(err))
            sys.exit()
        except ReferenceError as err:
            print("ReferenceError:", err)
            sys.exit()
        except Exception as e:
            print("Specified database already exists! Exiting installer. Error: ", str(e))
            sys.exit()

    def get_filepath(self):
        '''Prompts the user for the directory information'''

        path = get_input("Location of JP2 Images: ")
        while not os.path.isdir(path):
            print("That is not a valid directory! Please try again.")
            path = get_input("Location of JP2 Images: ")

        return path

    def get_database_type(self):
        ''' Prompts the user for the database type '''
        dbtypes = {1: "mysql", 2: "postgres"}

        while True:
            print("Please select the desired database to use for installation:")
            print("\t[1] MySQL")
            print("\t[2] PostgreSQL")
            choice = get_input("Choice: ")

            if choice not in ['1', '2']:
                print("Sorry, that is not a valid choice.")
            else:
                return dbtypes[int(choice)]

    def get_database_host(self):
        ''' Prompts the user for the database host '''

        dbhost = get_input("\tDatabase host [localhost]: ")

        # Default values
        if not dbhost: dbhost = "localhost"

        return dbhost

    def get_database_name(self):
        ''' Prompts the user for the database name '''

        dbname = get_input("\tDatabase name [helioviewer]: ")

        # Default values
        if not dbname: dbname = "helioviewer"

        return dbname

    def should_setup_db(self):
        ''' Prompts the user for the database type '''
        options = {1: True, 2: False}

        while True:
            print("Would you like to create the database schema used by Helioviewer.org?:")
            print("\t[1] Yes")
            print("\t[2] No")
            choice = get_input("Choice: ")

            if choice not in ['1', '2']:
                print("Sorry, that is not a valid choice.")
            else:
                return options[int(choice)]

    def get_database_info(self):
        ''' Gets database type and administrator login information '''
        import getpass

        while True:
            dbtype = self.get_database_type()
            dbuser = get_input("\tUsername: ")
            dbpass = getpass.getpass("\tPassword: ")

            # Default values
            if not dbuser:
                dbuser = "root"

            # MySQL?
            mysql = dbtype is "mysql"

            return dbuser,dbpass,mysql

    def get_new_user_info(self):
        ''' Prompts the user for the required database information '''

        # Get new user information (Todo 2009/08/24: validate input form)
        dbuser = get_input("\tUsername [helioviewer]: ")
        dbpass = get_input("\tPassword [helioviewer]: ")

        # Default values
        if not dbuser:
            dbuser = "helioviewer"
        if not dbpass:
            dbpass = "helioviewer"

        return dbuser, dbpass

    def print_greeting(self):
        ''' Prints a greeting to the user'''
        subprocess.call("clear", shell=True)

        print("""\
====================================================================
= Helioviewer Database Population Script                           =
= Last updated: 203/01/12                                          =
=                                                                  =
= This script processes JP2 images, extracts their associated      =
= meta-information and stores it away in a database.               =
=                                                                  =
====================================================================""")

# Python 3 compatibility work-around
if sys.version_info[0] >= 3:
    get_input = input
else:
    get_input = raw_input
