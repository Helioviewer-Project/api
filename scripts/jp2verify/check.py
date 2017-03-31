#!/usr/bin/env python
#-*- coding:utf-8 -*-
"""Helioviewer.org JP2 Transcoding check
Check recursively folder with JP2 files for any un-transcoded files and then convert it
Require: https://github.com/Helioviewer-Project/hvJP2K
"""
from __future__ import print_function

import os
import subprocess
import sys
import logging
import logging.handlers

import argparse
import signal

import xml.etree.cElementTree as cet
from lxml import etree as let
from lxml import isoschematron as iso
from jpylyzer import jpylyzer, config

from hvJP2K.jp2.data import *

#rootdir = '/mnt/data/jp2/SXT/2001/03/01/Al01'
#rootdir = '/mnt/data/jp2/TRACE'
#rootdir = '/mnt/data/jp2/AIA'
#rootdir = '/mnt/data/jp2/AIA/94/2010/06/02'
#rootdir = '/mnt/data/jp2/AIA/2017/03/01/94'
#fscheme = '/var/www/beta3.helioviewer.org/!work/jp2verify/scheme.sch'

#logfile = '/var/www/beta3.helioviewer.org/!work/jp2verify/transcoding.log'
#init_logger(logfile)

def main():
    """Main application"""

    # Parse and validate command-line arguments
    args = get_args()
    
    # Configure loggings
    if args.log is not None:
        logfile = os.path.abspath(args.log)
    else:
        logfile = "jp2check.log"
    
    init_logger(logfile)
    
    # Configure source directory
    if args.directory is not None:
        directorypath = os.path.abspath(args.directory)
    else:
        logging.error("Missing directory argument")
        exit(0)
    
    # Begin data check
    for subdir, dirs, files in os.walk(directorypath):
        for file in files:
            filename, file_extension = os.path.splitext(file)
        
            if file_extension.lower() == '.jp2':
                fpath = os.path.join(subdir, file)
            
                config.outputVerboseFlag = False
                config.extractNullTerminatedXMLFlag = True
            
                xmlTree = let.fromstring(cet.tostring(jpylyzer.checkOneFile(fpath)))
                schfile = 'scheme.sch'
                schema = iso.Schematron(let.parse(schfile))
             
                if schema.validate(xmlTree) is False:
                    try:
                        if 'AIA' in fpath:
                            _transcode(fpath, cprecincts=[128, 128])
                        else:
                            _transcode(fpath)
                    except KduTranscodeError, e:
                        logging.error("kdu_transcode: " + e.get_message())
                else:
                    logging.info("SKIPPING: " + fpath)
    
    logging.info("Exiting JP2verify")

def get_args():
    parser = argparse.ArgumentParser(description='Retrieves JPEG 2000 images.', add_help=False)
    parser.add_argument('-h', '--help', help='Show this help message and exit', action='store_true')
    parser.add_argument('-d', '--directory', metavar='directory', dest='directory', help='Directory path to check')
    parser.add_argument('-l', '--log-path', metavar='log', dest='log', help='Filepath to use for logging events. Defaults to HVPull working directory.')

    # Parse arguments
    args = parser.parse_args()

    # Print help
    if args.help:
        print_help(parser)
        sys.exit()
    
    return args

def print_help(parser):
    """Prints help information for HVPull"""
    parser.print_help()
    
    print('''
Example Usage:

jp2verify.py -d /path/to/folder -l /path/to/logfile.log

''') 

def init_logger(filepath):
    """Initializes logging"""
    # Check for logging directory
    directory, filename = os.path.split(os.path.expanduser(filepath))
    
    if directory is not "":
        if not os.path.exists(directory):
            os.makedirs(directory)
            
        os.chdir(directory)
        
    # %(asctime)s.%(msecs)03d
    formatter = logging.Formatter('%(asctime)s [%(levelname)s] %(message)s',
                                  datefmt='%Y-%m-%d %H:%M:%S')
    
    logger = logging.getLogger('')
    logger.setLevel(logging.INFO)
    
    # STDOUT logger
    console = logging.StreamHandler()

    # File logger
    rotate = logging.handlers.RotatingFileHandler(filename, 
                                                  maxBytes=10000000, backupCount=10)
    rotate.setFormatter(formatter)
    
    logger.addHandler(console)
    logger.addHandler(rotate)

def _transcode(filepath, corder='RPCL', orggen_plt='yes', cprecincts=None):
    """Transcodes JPEG 2000 images to allow support for use with JHelioviewer
    and the JPIP server"""
    tmp = filepath + '.tmp.jp2'

    # Base command

    command ='kdu_transcode -i %s -o %s' % (filepath, tmp)

    # Corder
    if corder is not None:
        command += " Corder=%s" % corder

    # ORGgen_plt
    if orggen_plt is not None:
        command += " ORGgen_plt=%s" % orggen_plt

    # Cprecincts
    if cprecincts is not None:
        command += " Cprecincts=\{%d,%d\}" % (cprecincts[0], cprecincts[1])

    # Hide output
    command += " >/dev/null"

    # Execute kdu_transcode (retry up to five times)
    num_retries = 0

    while not os.path.isfile(tmp) and num_retries <= 5:
        subprocess.call(command, shell=True)
        num_retries += 1

    # If transcode failed, raise an exception
    if not os.path.isfile(tmp):
        logging.info('File %s reported as not found.' % tmp)
        raise KduTranscodeError(filepath)

    # Remove old version and replace with transcoded one
    # OSError
    os.remove(filepath)
    logging.info('Removed %s ' % filepath)
    os.rename(tmp, filepath)
    logging.info('Renamed %s to %s' % (tmp, filepath))


if __name__ == "__main__":
    sys.exit(main())
