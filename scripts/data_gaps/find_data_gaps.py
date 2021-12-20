"""
Scans the database to find any data gaps over the given time range.

Run from the scripts folder via:
python -m data_gaps.find_data_gaps
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
                        help='Destination log file to place the gap report')

    args = parser.parse_args()
    config = ConfigParser()
    config.read(args.creds)
    args.creds = config
    return args
