"""ImageRetreivalDaemon"""
# Licensed under MOZILLA PUBLIC LICENSE Version 1.1
# Author: Keith Hughitt <keith.hughitt@nasa.gov>
# Author: Jack Ireland <jack.ireland@nasa.gov>
# pylint: disable=E1121
import sys
import stat
import grp
import datetime
import time
import logging
import os
import shutil
import traceback
from helioviewer.jp2 import process_jp2_images, BadImage, create_image_data, transcode, KduTranscodeError
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
        return self.servers[0].name in ['LMSAL', 'LMSAL2', 'JSOC']

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
            self.oldest_timestamp = starttime

            # If end time is specified, fill in data from start to end
            if endtime is not None:
                endtime = datetime.datetime.strptime(endtime, date_fmt)
                self.query(starttime, endtime)

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
            self.oldest_timestamp = starttime
            self.query(starttime, endtime)
            self.stop()

        # Begin main loop
        while not self.shutdown_requested:
            now = datetime.datetime.utcnow()
            starttime = self.servers[0].get_starttime()

            # get a list of files available
            # self.oldest_timestamp gets set by query() during the first run
            # before the main loop.
            self.query(starttime, now)

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
            # For LMSAL, HMI is queried at a 4 hour lag. We need to
            # make sure this query will include images 4 hours behind.
            # More specifically this resolves a problem where we query
            # a time range around 00:00:00 to 04:00:00. In this case,
            # the previous day is not queried, and so HMI images that are
            # still from the day before (because they're 4 hours behind starttime)
            # are would be ignored
            query_start_time = self._get_query_starttime(starttime)

            #print(browser, starttime, endtime)
            matches = self.query_server(browser, query_start_time, endtime)
            matches = self._dedupe_urls(matches)

            #print(matches)
            if len(matches) > 0:
                urls.append(matches)

        #print(urls)
        # Spread load across servers
        if len(urls) > 1:
            urls = self._balance_load(urls)

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
            if self.servers[0].name in ['LMSAL2']:
                new_urls.append(extra_filtered)
                if len(extra_filtered) > 0:
                    self.oldest_timestamp = self._get_oldest_image(extra_filtered)
            else:
                new_urls.append(filtered)
                if len(filtered) > 0:
                    self.oldest_timestamp = self._get_oldest_image(filtered)

        # check disk space
        if not self.sent_diskspace_warning:
            self._check_free_space()

        # acquire the data files
        self.acquire(new_urls)

    def _dedupe_urls(self, urls: list) -> list:
        """
        Deduplicates files in the given url list.
        This ensures that no files with the same name are ignored
        """
        known_files = []
        final_urls = []
        for url in urls:
            fname = os.path.basename(url)
            if fname not in known_files:
                known_files.append(fname)
                final_urls.append(url)
        return final_urls

    def _get_query_starttime(self, starttime):
        """
        Returns the starttime that should be queried from the data sources.
        i.e. starttime may be 2022-03-25 00:00:00, but we should still query
        2022-03-24 in order to catch HMI images from the day before.
        """
        if self.servers[0].name in ['LMSAL', 'LMSAL2']:
            hmi_delay = self.get_sdo_processing_delay()['hmi']
            return starttime - hmi_delay
        else:
            return starttime

    def _get_oldest_image(self, image_list):
        """
        Returns the oldest image out of the given list of image file names
        """
        oldest = self._get_datetime_from_file(image_list[0])
        for image in image_list:
            image_time = self._get_datetime_from_file(image)
            if (image_time < oldest):
                oldest = image_time
        return oldest

    def _get_datetime_from_file(self, file):
        return self.servers[0].get_datetime_from_file(file)

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
            elif self.is_sdo():
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

            # Check if any downloaders have failed, and quit if they have
            for downloader_threads in self.downloaders:
                if self.shutdown_requested:
                    break
                for downloader in downloader_threads:
                    if downloader.has_failed():
                        logging.error("Quitting due downloader failure")
                        self.shutdown()
                        break

            if not self.shutdown_requested:
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
                    # Make sure the full exception gets into the log
                    # so we can debug it.
                    logging.error(traceback.format_exc())
                    raise BadImage("HEADER")
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
                cprecincts = None
                if image_params['instrument'] == "AIA":
                    cprecincts = [128,128]
                transcoded = transcode(self.kdu_transcode, filepath, cprecincts=cprecincts)
                # Remove old version and replace with transcoded one
                # OSError
                os.remove(filepath)
                logging.info('Removed %s ' % filepath)
                os.rename(transcoded, filepath)
                logging.info('Renamed %s to %s' % (transcoded, filepath))
            except KduTranscodeError as e:
                logging.error("kdu_transcode: " + e.get_message())
                logging.error("Quitting due to kdu_transcode error")
                # Don't continue from here, it's very likely that
                # there's something else wrong causing the transcode
                # step to fail that needs to be investigated. Transcoding
                # is a required step for JHelioviewer to be able to use
                # the images.
                sys.exit(1)

            # Move to archive
            if image_params['observatory'] == "Hinode":
                directory = os.path.join(self.image_archive, image_params['nickname'], date_str, str(image_params['filter1']), str(image_params['filter2']))
            elif image_params['observatory'] == "RHESSI":
                directory = os.path.join(self.image_archive, image_params['nickname'], date_str, str(image_params['reconstruction_method']))
            else:
                directory = os.path.join(self.image_archive, image_params['nickname'], date_str, str(image_params['measurement']))

            dest = os.path.join(directory, filename)

            image_params['filepath'] = dest

            if not os.path.exists(directory):
                self.create_image_directory(directory)

            try:
                shutil.move(filepath, dest)
            except IOError:
                logging.error("Unable to move files to destination. Is there "
                              "enough free space?")
                # Do not proceed to insert these images into the database if
                # we were unable to move them to the image archive.
                sys.exit(1)

            # Add to list to send to main database
            images.append(image_params)

        # Add valid images to main Database
        process_jp2_images(images, self.image_archive, self._db, self._cursor, True, None, self._cursor_v2)

        logging.info("Added %d images to database", len(images))

        if len(corrupt) > 0:
            logging.info("Marked %d images as corrupt", len(corrupt))

    def get_helioviewer_group(self):
        """
        Returns the linux group id of the group used
        for managing the helioviewer archive. The group
        name used is 'helioviewer' if the group does not
        exist on the system, it will be created.
        """
        group_name = 'helioviewer'
        try:
            grp.getgrnam(group_name)
        except KeyError:
            # create the group if it doesn't exist
            os.system('groupadd {}'.format(group_name))

        return grp.getgrnam(group_name).gr_gid

    def create_image_directory(self, path):
        """
        Creates a directory with appropriate permissions
        for storing images. All parent directories up to
        and including PATH will be created with group
        write permissions set.

        args:
          path - The full path & name of the directory to be created
        """
        if not os.path.exists(path):
            permissions = stat.S_IRWXU | stat.S_IRWXG | stat.S_IROTH | stat.S_IXOTH
            user_id = os.getuid()
            directories = path.split(os.sep)
            # Traverse the directories to be created so that
            # group write permissions can be set on each one.
            fullpath = ""
            for directory in directories:
                try:
                    fullpath += directory + os.sep
                    # ignore trying to create /root
                    if fullpath == '/':
                        continue
                    # Once the paths to be created are reached,
                    # create the directories and set appropriate permissions.
                    if not os.path.exists(fullpath):
                        os.mkdir(fullpath)
                        try:
                            group_id = self.get_helioviewer_group()
                            os.chown(fullpath, user_id, group_id)
                            os.chmod(fullpath, mode=permissions)
                        except Exception as e:
                            # Not necessarily an error, things ought to still function, but
                            # admins may have permission to edit these files.
                            logging.warn(f"Unable to set group permissions on {fullpath}.")
                except Exception as e:
                    logging.error("Unable to create the directory '" +
                                  fullpath + "'. Please ensure that you "
                                  "have the proper permissions and try again.")
                    logging.error(f"Error: {str(e)}")
                    # Do not continue if we don't have a directory to place
                    # the files into
                    sys.exit(1)

    def send_email_alert(self, message):
        """Sends an email notification to the Helioviewer admin(s) when a
        one of the data sources becomes unreachable."""
        # If no server was specified, don't do anything
        if self.email_server == "":
            return

        # import email modules
        import smtplib
        from email.mime.multipart import MIMEMultipart
        from email.mime.text import MIMEText
        from email.utils import formatdate

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

    def _balance_load(self, urls):
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
                sys.exit(1)

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
            "ccor2": "CCOR2DataServer",
            "ccor1": "CCOR1DataServer",
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
            "hv_kcor": "HVKCORDataServer",
            "solar_orbiter": "SolarOrbiterDataServer",
            "suvi": "SUVIDataServer",
            "iris": "IRISDataServer",
            "hv_iris": "HvIRISDataServer",
            "halpha": "GongDataServer",
            "hv_rhessi": "HVRHESSIDataServer",
            "punch": "PUNCHDataServer",
            "local": "LocalDataServer",
            "hv": "HvDataServer",
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


"""ImageRetreivalDaemon"""
