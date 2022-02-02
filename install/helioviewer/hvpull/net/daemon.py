"""ImageRetreivalDaemon"""
# Licensed under MOZILLA PUBLIC LICENSE Version 1.1
# Author: Keith Hughitt <keith.hughitt@nasa.gov>
# Author: Jack Ireland <jack.ireland@nasa.gov>
# pylint: disable=E1121
import sys
import datetime
import time
import logging
import os
import subprocess
import shutil
import sunpy
from random import shuffle
from helioviewer.jp2 import process_jp2_images, BadImage, create_image_data
from helioviewer.db  import get_db_cursor, mark_as_corrupt
from helioviewer.hvpull.browser.basebrowser import NetworkError
from sunpy.time import is_time

try:
    import mysql.connector as mysqld
except ImportError:
    try:
        import MySQLdb as mysqld
    except ImportError:
        print("There is no such module installed")
        exit(0)

if (sys.version_info >= (3, 0)):
    import queue
else:
    import Queue as queue

class ImageRetrievalDaemon:
    """Retrieves images from the server as specified"""
    def __init__(self, servers, browse_method, download_method, conf):
        """Explain."""
        # MySQL/Postgres info
        self.dbhost = conf.get('database', 'dbhost')
        self.dbname = conf.get('database', 'dbname')
        self.dbuser = conf.get('database', 'dbuser')
        self.dbpass = conf.get('database', 'dbpass')
        # MySQL/Postgres info v2
        self.dbhost_v2 = conf.get('database_v2', 'dbhost_v2')
        self.dbname_v2 = conf.get('database_v2', 'dbname_v2')
        self.dbuser_v2 = conf.get('database_v2', 'dbuser_v2')
        self.dbpass_v2 = conf.get('database_v2', 'dbpass_v2')

        self.downloaders = []

        try:
            self._db, self._cursor = get_db_cursor(self.dbhost, self.dbname, self.dbuser, self.dbpass)
        except mysqld.OperationalError:
            logging.error("Unable to access MySQL. Is the database daemon running?")
            self.shutdown()
            self.stop()

        # v2 database
        if self.dbhost_v2 != "" and self.dbname_v2 != "":
            try:
                self._db_v2, self._cursor_v2 = get_db_cursor(self.dbhost_v2, self.dbname_v2, self.dbuser_v2, self.dbpass_v2)
            except mysqld.OperationalError:
                logging.error("Unable to access MySQL. Is the database daemon running (v2)?")
                self.shutdown()
                self.stop()
        else:
            self._db_v2 = None
            self._cursor_v2 = None

        # Email notification
        self.email_server = conf.get('notifications', 'server')
        self.email_from = conf.get('notifications', 'from')
        self.email_to = conf.get('notifications', 'to')

        # Warning flags
        self.sent_diskspace_warning = False

        # Maximum number of simultaneous downloads
        self.max_downloads = conf.getint('network', 'max_downloads')

        # Directories
        self.working_dir = os.path.expanduser(conf.get('directories', 'working_dir'))
        self.image_archive = os.path.expanduser(conf.get('directories', 'image_archive'))
        self.incoming = os.path.join(self.working_dir, 'incoming')
        self.quarantine = os.path.join(self.working_dir, 'quarantine')
        self.kdu_transcode = os.path.expanduser(conf.get('kakadu', 'kdu_transcode'))

        # Check directory permission
        self._init_directories()

        # Load data server, browser, and downloader
        self.servers = self._load_servers(servers)

        self.browsers = []
        self.downloaders = []
        self.queues = []

        # For each server instantiate a browser and one or more downloaders
        for server in self.servers:
            print("Creating browser with me")
            self.browsers.append(self._load_browser(browse_method, server))
            global queue
            queue = queue.Queue()
            self.queues.append(queue)
            self.downloaders.append([self._load_downloader(download_method, queue)
                                     for i in range(self.max_downloads)])

        # Shutdown switch
        self.shutdown_requested = False

    def is_sdo(self):
        """
        Returns true if the current server is an SDO server
        """
        return self.servers[0].name in ['LMSAL', 'LMSAL2']

    def get_sdo_processing_delay(self):
        return {"aia": datetime.timedelta(minutes=0), "hmi": datetime.timedelta(minutes=4*60)}

    def start(self, starttime=None, endtime=None, backfill=None):
        """Start daemon operation."""
        logging.info("Initializing HVPull")

        date_fmt = "%Y-%m-%d %H:%M:%S"

        # @TODO: Process urls in batches of ~1-500.. this way images start
        # appearing more quickly when filling in large gaps, etc.

        # @TODO: Redo handling of server-specific start time and pause
        # time
        #
        # @TODO: Send email notification when HVpull stops/exits for any reason?

        # Determine starttime and endtime to use
        if backfill is None:
            if starttime is not None:
                starttime = datetime.datetime.strptime(starttime, date_fmt)
            else:
                starttime = self.servers[0].get_starttime()

            # If end time is specified, fill in data from start to end
            if endtime is not None:
                endtime = datetime.datetime.strptime(endtime, date_fmt)
                self.query(starttime, endtime)
                self.sleep()

                return None
            else:
                # Otherwise, first query from start -> now
                now = datetime.datetime.utcnow()
                self.query(starttime, now)
                self.sleep()
        else:
            # Backfill process has been requested.  Look for data in the last
            # "backfill" days. In normal operations, only the most recent
            # data from each instrument is ingested.  If the pipeline halts for
            # some reason, then the the regular ingestion process can leave
            # gaps since it looks for data at some fixed time back from now.
            # The backfill process is a less regularly run process that looks
            # much further back for data that may have been missed.  This is
            # intended to be a relatively infrequently run data ingestion
            # process, and should be run as a cron job.
            starttime = datetime.datetime.utcnow() - datetime.timedelta(days=backfill[0])
            endtime = datetime.datetime.utcnow() - datetime.timedelta(days=backfill[1])
            self.query(starttime, endtime)
            self.stop()

        # Begin main loop
        while not self.shutdown_requested:
            now = datetime.datetime.utcnow()
            starttime = self.servers[0].get_starttime()

            # get a list of files available
            # self.newest_timestamp gets set by query() during the first run
            # before the main loop.
            self.query(self.newest_timestamp, now)

            self.sleep()

        # Shutdown
        self.stop()

    def sleep(self):
        """Sleep for some time before checking again for new images"""
        if self.shutdown_requested:
            return

        logging.info("Sleeping for %d minutes." % (self.servers[0].pause.total_seconds() / 60))
        time.sleep(self.servers[0].pause.total_seconds())

    def stop(self):
        logging.info("Exiting HVPull")
        sys.exit()

    def query(self, starttime, endtime):
        """Query and retrieve data within the specified range.

        Checks for data in the specified range and retrieves any new files.
        After execution is completed, the same range is checked again to see
        if any new files have appeared since the first execution. This continues
        until no new files are found (for xxx minutes?)
        """
        urls = []

        fmt = '%Y-%m-%d %H:%M:%S'

        logging.info("Querying time range %s - %s", starttime.strftime(fmt),
                                                    endtime.strftime(fmt))

        for browser in self.browsers:
            #print(browser, starttime, endtime)
            matches = self.query_server(browser, starttime, endtime)
            #print(matches)
            if len(matches) > 0:
                urls.append(matches)

        #print(urls)
        # Remove duplicate files, randomizing to spread load across servers
        if len(urls) > 1:
            urls = self._deduplicate(urls)

        # Filter out files that are already in the database
        new_urls = []

        for url_list in urls:
            filtered = None

            while filtered is None:
                try:
                    # Filter by time range
                    filtered = self._filter_files_by_time(url_list, starttime, endtime)
                    filtered = list(filter(self._filter_new, filtered))
                except mysqld.OperationalError:
                    # MySQL has gone away -- try again in 5s
                    logging.warning(("Unable to access database to check for file existence. Will try again in 5 seconds."))
                    time.sleep(5)

                    # Try and reconnect

                    # @note: May be a good idea to move the reconnect
                    # functionality to the db module and have it occur
                    # for all queries.
                    try:
                        self._db, self._cursor = get_db_cursor(self.dbhost, self.dbname, self.dbuser, self.dbpass)
                    except:
                        pass


            ################################
            # Temporarily slow down the cadence of the data from certain instruments
            # Apply the extra filtering to the high-volume data only.
            # Note that the backfill operations should also be killed when using this option
            # Also the look-back time should not exceed the time between searches

            # Sample size
            stride = 20

            # Number of strided files
            n_strided = 0

            # We want to subsample data from these high volume instruments
            high_volume_instruments = ('aia', 'hmi')

            # Some of these high volume data are slower behind real time than others
            processing_delay = self.get_sdo_processing_delay()

            # Storage for the ub-sampled URLs
            strided = dict()

            # URLs that do not include specified instruments
            does_not_include_specified_instruments = []

            for instrument in high_volume_instruments:
                # Dictionary contains the results for the next instrument
                strided[instrument] = []

                # Delay
                delay = processing_delay[instrument]

                # These URLs are within the time range
                includes_specified_instruments = []
                for this_url in filtered:
                    if instrument in this_url.lower():
                        # Calculate the observation time of the
                        url_time = self._get_datetime_from_file(this_url)

                        # Get all the URLs within a specified time range
                        if (starttime - delay <= url_time) and (url_time <= endtime - delay):
                            includes_specified_instruments.append(this_url)
                print("Time filtered file count: %d" % len(includes_specified_instruments))
                # Take a subsample of the files for this instrument
                strided[instrument] = includes_specified_instruments[::stride]
                n = len(strided[instrument])
                n_strided = n_strided + n
                logging.info('Number of files from ' + instrument + ' = ' + str(n))

            high_volume_instrument_string = ' '.join([str(elem) for elem in high_volume_instruments])
            logging.info('Number of files available in time range from high volume instruments= ' + str(len(filtered)))
            logging.info('High volume instruments are = ' + high_volume_instrument_string)
            logging.info('Stride value = ' + str(stride))
            logging.info('Number of strided files from high volume instruments = ' + str(n_strided))
            # Create a combined list
            extra_filtered = []
            for instrument in high_volume_instruments:
                extra_filtered.extend(strided[instrument])
            extra_filtered.extend(does_not_include_specified_instruments)
            logging.info('Number of new URLS = ' + str(len(extra_filtered)))

            ################################
            if self.servers[0].name in ['LMSAL', 'LMSAL2']:
                new_urls.append(extra_filtered)
                self.newest_timestamp = self._get_newest_image(extra_filtered)
            else:
                new_urls.append(filtered)
                self.newest_timestamp = self._get_newest_image(filtered)

        # check disk space
        if not self.sent_diskspace_warning:
            self._check_free_space()

        # acquire the data files
        self.acquire(new_urls)

    def _get_newest_image(self, image_list):
        """
        Returns the newest image out of the given list of image file names
        """
        newest = self._get_datetime_from_file(image_list[0])
        for image in image_list:
            image_time = self._get_datetime_from_file(image)
            if (image_time > newest):
                newest = image_time
        return newest

    def _get_datetime_from_file(self, file):
        url_filename = os.path.basename(file)
        url_datetime = url_filename[0:20]
        return datetime.datetime.strptime(url_datetime, '%Y_%m_%d__%H_%M_%S')


    def _filter_files_by_time(self, files, starttime, endtime):
        """
        Days on the server are already organized by day of the month.
        This function will further filter through the file names for
        hours:minutes:seconds time.
        """
        filtered_list = []
        hmi_delay = self.get_sdo_processing_delay()['hmi']
        for file in files:
            timestamp = self._get_datetime_from_file(file)
            if (timestamp > starttime) and (timestamp < endtime):
                filtered_list.append(file)
            if self.is_sdo():
                # SDO contains AIA and HMI, while AIA is up-to-date, HMI lags by
                # roughly 4 hours, so this accounts for that when using the LMSAL
                # data servers
                modified_start = starttime - hmi_delay
                modified_end = endtime - hmi_delay
                if (timestamp > modified_start) and (timestamp < modified_end):
                    filtered_list.append(file)
        return filtered_list

    def query_server(self, browser, starttime, endtime):
        """Queries a single server for new files"""
        directories = browser.get_directories(starttime, endtime)

        # Get a sorted list of available JP2 files via browser
        files = []

        # Check each remote directory for new files
        for directory in directories:
            if self.shutdown_requested:
                return []

            matches = None
            num_retries = 0

            logging.info('(%s) Scanning %s' % (browser.server.name, directory))

            # Attempt to read directory contents. Retry up to 10 times
            # if failed and then notify admin
            while matches is None:
                if self.shutdown_requested:
                    return []

                try:
                    matches = browser.get_files(directory, "jp2")

                    files.extend(matches)
                except NetworkError:
                    if num_retries >= 3 * 1440:
                        logging.error("Unable to reach %s. Shutting down HVPull.",
                                      browser.server.name)
                        msg = "Unable to reach %s. Is the server online?"
                        self.send_email_alert(msg % browser.server.name)
                        self.shutdown()
                    else:
                        msg = "Unable to reach %s. Will try again in 60 seconds."
                        if num_retries > 0:
                            msg += " (retry %d)" % num_retries
                        logging.warning(msg, browser.server.name)
                        time.sleep(60)
                        num_retries += 1

        return files

    def acquire(self, urls):
        """Acquires all the available files."""
        # If no new files are available do nothing
        if not urls:
            logging.info("Found no new files.")
            return

        n = sum(len(x) for x in urls)

        # Keep track of progress
        total = n
        counter = 0

        logging.info("Found %d new files", n)

        # Download files
        while n > 0:
            finished = []

            # Download files 100 at a time to avoid blocking shutdown requests
            # and to allow images to be added to database sooner
            for i, server in enumerate(list(urls)):
                for j in range(100): #pylint: disable=W0612
                    if len(list(server)) > 0:

                        url = server.pop()

                        finished.append(url)

                        counter += 1.

                        self.queues[i].put([self.servers[i].name, (counter / total) * 100, url])

                        n -= 1

            for q in self.queues:
                q.join()

            self.ingest(finished)

            if self.shutdown_requested:
                break

    def ingest(self, urls):
        """
        Add images to helioviewer data db.
          (1) Make sure the file exists
          (2) Make sure the file is 'good', and quarantine if it is not.
          (3) Apply the ESA JPIP encoding.
          (4) Ingest
          (5) Update database to say that the file has been successfully
              'ingested'.
        """
        # Get filepaths
        filepaths = []
        images = []
        corrupt = []

        for url in urls:
            path = os.path.join(self.incoming, os.path.basename(url)) # @TODO: Better path computation
            if os.path.isfile(path):
                filepaths.append(path)

        # Add to hvpull/Helioviewer.org databases
        for filepath in filepaths:
            filename = os.path.basename(filepath)

            # Parse header and validate metadata
            try:
                try:
                    image_params = create_image_data(filepath)
                except:
                    raise BadImage("HEADER")
                    logging.warn('BadImage("HEADER") error raised')
                self._validate(image_params)
            except BadImage as e:
                logging.warn("Quarantining invalid image: %s", filename)
                logging.warn("BadImage found; error message= %s", e.get_message())
                shutil.move(filepath, os.path.join(self.quarantine, filename))
                mark_as_corrupt(self._cursor, filename, e.get_message())
                corrupt.append(filename)
                continue

            # If everything looks good, move to archive and add to database
            # print image_params['date']
            date_str = image_params['date'].strftime('%Y/%m/%d')

            # The files must be transcoded in order to work with JHelioviewer.
            # Therefore, any problem with the transcoding process must raise
            # an error.
            try:
                if image_params['instrument'] == "AIA":
                    self._transcode(filepath, cprecincts=[128, 128])
                else:
                    self._transcode(filepath)
            except KduTranscodeError as e:
                logging.error("kdu_transcode: " + e.get_message())

            # Move to archive
            if image_params['observatory'] == "Hinode":
                directory = os.path.join(self.image_archive, image_params['nickname'], date_str, str(image_params['filter1']), str(image_params['filter2']))
            else:
                directory = os.path.join(self.image_archive, image_params['nickname'], date_str, str(image_params['measurement']))

            dest = os.path.join(directory, filename)

            image_params['filepath'] = dest

            if not os.path.exists(directory):
                try:
                    os.makedirs(directory)
                except OSError:
                    logging.error("Unable to create the directory '" +
                                  directory + "'. Please ensure that you "
                                  "have the proper permissions and try again.")
                    self.shutdown_requested = True

            try:
                shutil.move(filepath, dest)
            except IOError:
                logging.error("Unable to move files to destination. Is there "
                              "enough free space?")
                self.shutdown_requested = True

            # Add to list to send to main database
            images.append(image_params)

        # Add valid images to main Database
        process_jp2_images(images, self.image_archive, self._db, self._cursor, True, None, self._cursor_v2)

        logging.info("Added %d images to database", len(images))

        if len(corrupt) > 0:
            logging.info("Marked %d images as corrupt", len(corrupt))

    def send_email_alert(self, message):
        """Sends an email notification to the Helioviewer admin(s) when a
        one of the data sources becomes unreachable."""
        # If no server was specified, don't do anything
        if self.email_server is "":
            return

        # import email modules
        import smtplib
        from email.MIMEMultipart import MIMEMultipart
        from email.MIMEText import MIMEText
        from email.Utils import formatdate

        msg = MIMEMultipart()
        msg['From'] = self.email_from
        msg['To'] = self.email_to
        msg['Date'] = formatdate()
        msg['Subject'] = "HVPull - Remote Server Inaccessible!"

        msg.attach(MIMEText(message))

        # Expand email recipient list
        recipients = [x.lstrip().rstrip() for x in self.email_to.split(",")]

        smtp = smtplib.SMTP(self.email_server)
        smtp.sendmail(self.email_from, recipients, msg.as_string() )
        smtp.close()

    def shutdown(self):
        print("Stopping HVPull. This may take a few minutes...")
        self.shutdown_requested = True

        for server in self.downloaders:
            for downloader in server:
                downloader.stop()

    def _transcode(self, filepath, corder='RPCL', orggen_plt='yes', cprecincts=None):
        """Transcodes JPEG 2000 images to allow support for use with JHelioviewer
        and the JPIP server"""
        tmp = filepath + '.tmp.jp2'

        # Base command

        command ='%s -i %s -o %s' % (self.kdu_transcode, filepath, tmp)

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

    def _check_free_space(self):
        """Checks the amount of free space on the data volume and emails admins
        the first time HVPull detects low disk space"""
        s = os.statvfs(self.image_archive)

        # gigabytes available
        gb_avail = (s.f_bsize * s.f_bavail) / 2**30

        # if less than 500, alert admins
        if gb_avail < 500:
            msg = "Warning: Running low on disk space! 500 GB remaining"
            self.send_email_alert(msg)
            self.sent_diskspace_warning = True

    def _deduplicate(self, urls):
        """When working with multiple files, this function will ensure that
        each file is only downloaded once.

        Sorting is preserved and load is distributed evenly across each server.
        """
        # Filenames
        files = [[os.path.basename(url) for url in x] for x in urls]

        # Number of servers and total number of remote files matched
        m = len(self.servers)
        n = sum(len(x) for x in files)

        # Counters to keep track of sub-list iteration
        counters = [0] * m

        # Loop through files, switching between servers on each iteration
        for i in range(n):
            idx = i % m  # Server index

            if len(files[idx]) > counters[idx]:
                value = files[idx][counters[idx]]

                # Skip over files that have been set to None
                while value is None:
                    counters[idx] += 1

                    if(len(files[idx]) > counters[idx]):
                        value = files[idx][counters[idx]]
                    else:
                        break

                if value is None:
                    continue

                filename = os.path.basename(value)

                # Ignore file on other servers if it exists
                for i, file_list in enumerate(files):
                    if i == idx:
                        continue

                    if filename in file_list:
                        j = files[i].index(filename)
                        files[i][j] = None
                        urls[i][j] = None

            counters[idx] += 1

        # Remove all entries set to None
        new_list = []

        for url_list in urls:
            new_list.append([x for x in url_list if x is not None])

        return new_list

    def _validate(self, params):
        """Filters out images that are known to have problems using information
        in their metadata"""

        # Make sure the time can be understood
        if not is_time(params['date']):
            raise BadImage("DATE")

        # AIA
        if params['detector'] == "AIA":
            if params['header'].get("IMG_TYPE") == "DARK":
                raise BadImage("DARK")
            if float(params['header'].get('PERCENTD')) < 50:
                raise BadImage("PERCENTD")
            if str(params['header'].get('WAVE_STR')).endswith("_OPEN"):
                raise BadImage("WAVE_STR")

        # LASCO
        if params['instrument'] == "LASCO":
            hcomp_sf = params['header'].get('hcomp_sf')

            if ((params['detector'] == "C2" and hcomp_sf == 32) or
                (params['detector'] == "C3" and hcomp_sf == 64)):
                    raise BadImage("WrongMask")

    def _init_directories(self):
        """Checks to see if working directories exists and attempts to create
        them if they do not."""
        for d in [self.working_dir, self.image_archive, self.incoming, self.quarantine]:
            if not os.path.exists(d):
                os.makedirs(d)
            elif not (os.path.isdir(d) and os.access(d, os.W_OK)):
                print("Unable to write to specified directories specified in "
                      "settings.cfg.")
                sys.exit()

    def _load_servers(self, names):
        """Loads a data server"""
        servers = []

        for name in names:
            server = self._load_class('helioviewer.hvpull.servers',
                                      name, self.get_servers().get(name))
            servers.append(server())

        return servers

    def _load_browser(self, browse_method, uri):
        """Loads a data browser"""
        cls = self._load_class('helioviewer.hvpull.browser', browse_method,
                               self.get_browsers().get(browse_method))
        return cls(uri)

    def _load_downloader(self, download_method, queue):
        """Loads a data downloader"""
        cls = self._load_class('helioviewer.hvpull.downloader', download_method,
                               self.get_downloaders().get(download_method))
        downloader = cls(self.incoming, queue)

        downloader.setDaemon(True)
        downloader.start()

        return downloader

    def _load_class(self, base_package, packagename, classname):
        """Dynamically loads a class given a set of strings indicating its
        location"""
        # Import module
        modname = "%s.%s" % (base_package, packagename)
        __import__(modname)

        # Instantiate class and return
        return getattr(sys.modules[modname], classname)

    def _filter_new(self, url):
        """For a given list of remote files determines which ones have not
        yet been acquired."""
        filename = os.path.basename(url)

        # Check to see if image is in `data` table
        self._cursor.execute("SELECT COUNT(*) FROM data WHERE filename='%s'" % filename)
        if self._cursor.fetchone()[0] != 0:
            return False

        # Check to see if image is in `corrupt` table
        #print('Remove comments characters to reactivate the code beneath when in production!!!')
        self._cursor.execute("SELECT COUNT(*) FROM corrupt WHERE filename='%s'" % filename)
        if self._cursor.fetchone()[0] != 0:
            return False

        return True

    @classmethod
    def get_servers(cls):
        """Returns a list of valid servers to interact with"""
        return {
            "lmsal2": "LMSALDataServer2",
            "lmsal": "LMSALDataServer",
            "soho": "SOHODataServer",
            "hv_soho": "HVSOHODataServer",
            "stereo": "STEREODataServer",
            "hv_stereo": "HVSTEREODataServer",
            "jsoc": "JSOCDataServer",
            "rob": "ROBDataServer",
            "uio": "UIODataServer",
            "trace": "TRACEDataServer",
            "xrt": "XRTDataServer",
            "kcor": "KCORDataServer",
            "hv_kcor": "HVKCORDataServer"
        }

    @classmethod
    def get_browsers(cls):
        """Returns a list of valid data browsers to interact with"""
        return {
        "httpbrowser": "HTTPDataBrowser",
        "localbrowser": "LocalDataBrowser"
        }

    @classmethod
    def get_downloaders(cls):
        """Returns a list of valid data downloaders to interact with"""
        return {
            "urllib": "URLLibDownloader",
            "localmove": "LocalFileMove"
        }


class KduTranscodeError(RuntimeError):
    """Exception to raise an image cannot be transcoded."""
    def __init__(self, message=""):
        self.message = message

    def get_message(self):
        return self.message

"""ImageRetreivalDaemon"""
