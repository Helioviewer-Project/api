"""urllib2-based file downloader"""
import sys
import os
import logging
import time
from urllib.request import urlopen, Request
from urllib.error import URLError, HTTPError
from .downloader_interface import Downloader

class URLLibDownloader(Downloader):
    def __init__(self, incoming, queue):
        """Creates a new URLLibDownloader"""
        super().__init__(incoming, queue)

        self.failure_count = 0

        # The fields below are set by the Downloader parent class
        # self.incoming
        # self.queue

    def _handle_download_failure(self):
        # At 10 failures, stop processing
        self.failure_count += 1
        if (self.failure_count >= 10):
            self.flag_failure()

    def process(self, item):
        """Downloads the file at the specified URL"""
        #ValueError: too many values to unpack
        server, percent, url = item

        # @TODO: compute path to download file to...

        # Location to save file to
        filepath = os.path.join(self.incoming, os.path.basename(url))

        # Create sub-directory if it does not already exist
        if not os.path.exists(os.path.dirname(filepath)):
            try:
                os.makedirs(os.path.dirname(filepath))
            except OSError:
                pass

        #Write to our local file
        try:
            # TODO: should urlretrieve be used instead?
            t1 = time.time()

            remote_file = urlopen(Request(url))

            file_contents = remote_file.read()

            t2 = time.time()

            mbps = (len(file_contents) / 10e5) / (t2 - t1)
            logging.info("(%s) Downloaded %s (%0.3f MB/s) [%0.2f%%]", server, url, mbps, percent)

        except URLError:
            # If download fails, add back into queue and try again later
            logging.warning("Failed to download %s. Adding to end of queue to retry later.", url)
            self.queue.put([server, percent, url])
            self._handle_download_failure()
        except:
            logging.warning("Failed to download %s.", url)
            self._handle_download_failure()
        else:
            # Open our local file for writing
            # @TODO: handle full disk scenario:
            # IOError: [Errno 28] No space left on device
            local_file = open(filepath, "wb")
            local_file.write(file_contents)
            local_file.close()
