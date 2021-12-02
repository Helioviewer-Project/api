"""
Gets the list of images from the database and confirms they exist on disk

Note: run this script from the scripts directory with the following command:
python3 -m jp2verify.validate_db
"""

import os
import sys
import argparse

from builtins import input # Python 2/3 compatibility
from configparser import ConfigParser

from utils.one_line_logger import OneLineLogger
from utils.database_pager import DatabasePager
from utils import database

GET_COUNT_SQL = "SELECT count(id) FROM data"
GET_FILES_SQL = "SELECT filepath, filename FROM data"

def _parse_args():
    parser = argparse.ArgumentParser(description='Validates image information on disk against information in the database')
    parser.add_argument('-c', '--creds', type=str, required=True,
                        help='ini file that contains database login credentials in the database section')
    parser.add_argument('-l', '--log', type=str, required=True,
                        help='Destination log file')
    parser.add_argument('-d', '--data', type=str, required=True,
                        help='Path to images folder')

    args = parser.parse_args()
    config = ConfigParser()
    config.read(args.creds)
    args.creds = config
    return args

def check_files_exist_on_disk(cursor, logger, datapath):
    pager = DatabasePager(cursor, GET_FILES_SQL, GET_COUNT_SQL)
    total_images = pager.get_count()

    logger.log("Checking database files against data on disk...")
    logger.log("This may take a while.")
    logger.lock_position()

    # Iterate over files and check if they exist on disk
    count = 0
    query_list = pager.get_all()
    for row in query_list:
        image_path = row[0] + os.sep + row[1]
        count += 1
        # Log progress
        logger.log("Processing image %d of %s" % (count, total_images))
        # Concatenate the db file path with the data directory
        full_image_path = datapath + image_path
        file_exists = os.path.exists(full_image_path)
        # If the file doesn't exist, add it to the missing file list
        if not file_exists:
            logger.log(full_image_path)
            logger.lock_position()

def main():
    args = _parse_args()
    logger = OneLineLogger(args.log)
    credentials = args.creds['database']
    cursor = database.get_dbcursor(dbname=credentials['database'], dbuser=credentials['username'], dbpass=credentials['password'])

    check_files_exist_on_disk(cursor, logger, args.data)

    # Close db connection
    cursor.close()

if __name__ == "__main__":
    main()