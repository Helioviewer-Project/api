"""
Gets the list of images from the database and confirms they exist on disk

Note: run this script from the scripts directory with the following command:
python3 -m jp2verify.validate_db
"""

import os
import sys

from utils.one_line_logger import OneLineLogger
from utils import database

from builtins import input # Python 2/3 compatibility

GET_COUNT_SQL = "SELECT count(id) FROM data"
GET_FILES_SQL = "SELECT filepath, filename FROM data LIMIT %s, %s"

class DataPager:
    """
    Helper class to query the database in chunks to limit memory usage
    """

    def __init__(self, cursor):
        self.cursor = cursor
        self.skip = 0
        # Modify this take value to optimize for memory usage.
        # Default is set to query 5 million rows.
        # Assume each row is approximately 100 bytes (which is close to accurate for our file names)
        # then 5,000,000 * 100 bytes = approximately 500 megabytes of memory.
        self.take = 5000000
        self._load_next_page()
    
    def get_image_count(self):
        self.cursor.execute(GET_COUNT_SQL)
        result = self.cursor.fetchone()
        return result[0]
    
    def _load_next_page(self):
        """
        Executes the query to get self.take # of rows
        """
        count = self.cursor.execute(GET_FILES_SQL % (self.skip, self.take))
        self.skip += self.take
        return count
    
    def _get_row(self):
        """
        Returns all database rows sequentially. Each call to this function
        will return the next row.

        Returns None when all rows in the database have been retrieved.
        otherwise this will return the next row.
        """

        # Get a row from the database
        row = self.cursor.fetchone()
        # if the row is not None
        if row is not None:
            # then return it
            return row
        else:
            # otherwise attempt to query another chunk
            self._load_next_page()
            # Check the row again
            row = self.cursor.fetchone()
            # if it is not none, return it
            if row is not None:
                return row
            # if it is still none, then there are no more rows.
            return None
    
    def generate_image_list(self):
        """
        Generator function for iterating through all files in the database.
        Considering the database can be very large, it is not practical to
        hold all file names in memory at once.
        """

        while True:
            # Get one row from the database
            row = self._get_row()
            # _get_row will return None once we've gone through all rows.
            if row is None:
                break
            # Concatenate filepath and filename
            image_path = row[0] + os.path.sep + row[1]
            # Return the image path, generator style
            yield image_path
        

def _get_log_file():
    """
    Get the path to the log file to write to.
    """
    log_path = input("Output file (missing.txt): ") or "missing.txt"
    return log_path

def _get_data_path():
    """
    Get the path to image data root directory from the user
    """
    data_path = input("Path to data (/var/www/jp2): ") or "/var/www/jp2"
    return data_path

def main():
    data_path = _get_data_path()
    log_file = _get_log_file()
    logger = OneLineLogger(log_file)
    cursor = database.get_dbcursor()

    missing_files = []
    # Get list of files from the data table
    logger.log("Getting list of files")
    pager = DataPager(cursor)
    total_images = pager.get_image_count()
    image_list = pager.generate_image_list()

    logger.log("Checking database files against data on disk...")
    logger.log("This may take a while.")
    logger.lock_position()

    # Iterate over files and check if they exist on disk
    count = 0
    for image_path in image_list:
        count += 1
        # Log progress
        logger.log("Processing image %d of %s" % (count, total_images))
        # Concatenate the db file path with the data directory
        full_image_path = data_path + image_path
        file_exists = os.path.exists(full_image_path)
        # If the file doesn't exist, add it to the missing file list
        if not file_exists:
            logger.log(full_image_path)
            logger.lock_position()
    # Close db connection
    cursor.close()

if __name__ == "__main__":
    main()