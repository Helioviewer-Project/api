"""Shared database functions"""
import sys
import getpass
import mysql.connector

def get_dbinfo():
    """Gets database type and administrator login information"""
    while True:
        if (sys.version_info >= (3, 0)):
            dbhost = input("    Hostname [localhost]: ") or "localhost"
            dbname = input("    Database [helioviewer]: ") or "helioviewer"
            dbuser = input("    Username [helioviewer]: ") or "helioviewer"
        else:
            dbhost = raw_input("    Hostname [localhost]: ") or "localhost"
            dbname = raw_input("    Database [helioviewer]: ") or "helioviewer"
            dbuser = raw_input("    Username [helioviewer]: ") or "helioviewer"
        
        dbpass = getpass.getpass("    Password: ")

        if not check_db_info(dbuser, dbpass, dbhost, dbname):
            print("Unable to connect to the database. Please check your "
                  "login information and try again.")
        else:
            return dbname, dbuser,dbpass

def check_db_info(dbuser, dbpass, dbhost, dbname ):
    """Validate database login information"""
    try:
        db = mysql.connector.connect(user=dbuser, password=dbpass, host=dbhost, database=dbname)
    except mysql.connector.Error as e:
        print(e)
        return False

    db.close()
    return True

def get_dbcursor():
    """Prompts the user for database info and returns a database cursor"""
    print("Please enter existing database login information:")
    dbhost, dbname, dbuser, dbpass = get_dbinfo()

    db = mysql.connector.connect(user=dbuser, password=dbpass, host=dbhost, database=dbname)

    db.autocommit(True)
    return db.cursor()