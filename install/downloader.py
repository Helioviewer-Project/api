#!/usr/bin/env python
#-*- coding:utf-8 -*-
"""Helioviewer.org JP2 Download Daemon (HVPull)
Code to pull files from remote and local locations

Terminology:

servers: locations that provide data available for download
browsers: methods of browsing the data available at a server
downloaders: methods of acquiring data from a server

The user specifies one or more servers, a browse method, and a download method.
The code then browses the servers and collects information on the
data available.  The user can then define what is to be done with the
data at the servers.  For example, we can download the difference
between the data that has already been downloaded compared to the data
on the servers.  We could also specify that a certain range of data
has to be re-downloaded again, over-writing previous data.

@TODO 05/31/2012: Email notifications for data outages

"""
import os
import sys
import signal
import logging
import argparse
from helioviewer import init_logger
from helioviewer.hvpull.net.daemon import ImageRetrievalDaemon

import configparser

def main():
    """Main application"""

    # Parse and validate command-line arguments
    args = get_args()
    validate_args(args, ImageRetrievalDaemon.get_servers(),
                  ImageRetrievalDaemon.get_browsers(),
                  ImageRetrievalDaemon.get_downloaders())

    # Parse configuration file
    conf = get_config(args.config)

    # Configure loggings'
    if args.log is not None:
        logfile = os.path.abspath(args.log)
    else:
        logfile = os.path.join(conf.get('directories', 'working_dir'),
                               "log/hvpull.log")
    init_logger(logfile)

    # Initialize daemon
    daemon = ImageRetrievalDaemon(args.servers, args.browse_method, args.download_method, conf)

    # Signal handlers
    def on_quit(signum, frame=None): # pylint: disable=W0613
        daemon.shutdown()

    # Signal handlers
    signal.signal(signal.SIGQUIT, on_quit)
    signal.signal(signal.SIGINT, on_quit)

    # Begin data retrieval
    daemon.start(args.start, args.end, args.backfill)

    logging.info("Finished processing all files in requested time range")
    logging.info("Exiting HVPull")

def get_config(filepath):
    """Load configuration file"""
    config = configparser.ConfigParser()

    basedir = os.path.dirname(os.path.realpath(__file__))
    default_userconfig = os.path.join(basedir, 'settings/settings.cfg')

    if filepath is not None and os.path.isfile(filepath):
        config.read(filepath)
    elif os.path.isfile(default_userconfig):
        config.read(default_userconfig)
    else:
        config.read(os.path.join(basedir, 'settings/settings.example.cfg'))

    return config

def get_args():
    parser = argparse.ArgumentParser(description='Retrieves JPEG 2000 images.', add_help=False)
    parser.add_argument('-h', '--help', help='Show this help message and exit', action='store_true')
    parser.add_argument('-d', '--data-servers', dest='servers',
                        help='Data servers from which data should be retrieved', default='lmsal')
    parser.add_argument('-b', '--browse-method', dest='browse_method', default='http',
                        help='Method for locating files on servers (default: http)')
    parser.add_argument('-m', '--download-method', dest='download_method', default='urllib',
                        help='Method for retrieving files on servers (default: urllib)')
    parser.add_argument('-s', '--start', metavar='date', dest='start',
                        help='Search for data with observation times later than this date/time (default: 24 hours ago)')
    parser.add_argument('-e', '--end', metavar='date', dest='end',
                        help='Search for data with observation times earlier than this date/time (default: repeats indefinitely using updated UT)')
    parser.add_argument('-c', '--config-file', metavar='file', dest='config',
                        help='Full path to hvpull user defined general configuration file')
    parser.add_argument('-l', '--log-path', metavar='log', dest='log',
                        help='Filepath to use for logging events. Defaults to HVPull working directory.')
    parser.add_argument('-f', '--backfill', metavar='N_days', dest='backfill', nargs=2, type=int,
                        help='Search for data with observation times starting at "now - N_days_0" and ending at "now - N_days_1".')

    # Parse arguments
    args = parser.parse_args()

    # Print help
    if args.help:
        print_help(parser)
        sys.exit()

    # Append browser to browse_method
    args.browse_method += "browser"

    # Parse servers
    args.servers = args.servers.split(",")

    return args

def validate_args(args, servers, browsers, downloaders):
    """Validate arguments"""
    for server in args.servers:
        if server not in servers:
            print ("Invalid data server specified. Valid server choices include:")
            for i in servers.keys():
                print (i)
            sys.exit()
    if args.browse_method not in browsers:
        print ("Invalid browse method specified. Valid browse methods include:")
        for i in browsers.keys():
            print (i)
        sys.exit()
    elif args.download_method not in downloaders:
        print ("Invalid download method specified. Valid download methods include:")
        for i in downloaders.keys():
            print (i)
        sys.exit()

def print_help(parser):
    """Prints help information for HVPull"""
    parser.print_help()

    print('''
Example Usage:

1. downloader.py

Default behavior: daemon is initialized, retrieves all data from most recent 24 hours
and then continues running and retrieving data until stopped by user.

2. downloader.py --start="2011-10-31 00:00:00"

Similar to above, but retrieves all data from Oct 31, 2011 onward.

3. downloader.py -s "1900-1-1 00:00:00" -l /var/log/hvpull-server1.log

Using a very early date has the effect of pulling all available data.

4. downloader.py -s "2011-10-27 00:00:00" -e "2011-10-31 00:00:00"

If an end date is specified, HVPull will stop execution once all of the data
between the specified date (end-date - 24 hrs if none is specified) and end-date
has been retrieved.

5. downloader.py -b local -m localmove -d soho

You can move local files in a local directory to Helioviewer. This moves SOHO
files from their directory into Helioviewer.  It is assumed that the SOHO files
are on the same file system as Helioviewer.  Note that using "-m localmove" does
not imply the use of "-b local".  The browse method "-b local" must be specified
when using "-m localmove".

6. downloader.py -f 7 1

Look for data in the time range from current UTC (now) minus seven days to
current UTC (now) minus 1 day.

''')

if __name__ == "__main__":
    sys.exit(main())
