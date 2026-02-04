"""moves files from one location on the local file system to another"""
import os
import logging
import threading
import time
import shutil
from .downloader_interface import Downloader

# Maximum number of retry attempts per file before giving up
MAX_RETRY_ATTEMPTS = 3

class LocalFileMove(Downloader):
    def __init__(self, incoming, queue):
        """Creates a new LocalFileMover"""
        super().__init__(incoming, queue)

        # The fields below are set by the Downloader parent class
        # self.incoming
        # self.queue

    def process(self, item):
        """Downloads the file at the specified URI

        Item format: [server, percent, uri] or [server, percent, uri, retry_count]
        The retry_count is optional and defaults to 0 for backward compatibility.
        """
        # Support both old format (3 elements) and new format (4 elements with retry count)
        if len(item) == 3:
            server, percent, uri = item
            retry_count = 0
        else:
            server, percent, uri, retry_count = item

        # @TODO: compute path to download file to...

        # Location to save file to
        filepath = os.path.join(self.incoming, os.path.basename(uri))

        # Create sub-directory if it does not already exist
        if not os.path.exists(os.path.dirname(filepath)):
            try:
                os.makedirs(os.path.dirname(filepath))
            except OSError:
                pass
        #Attempt to move the file
        try:
            t1 = time.time()
            shutil.move(uri, filepath)
            t2 = time.time()
            logging.info("(%s) locally moved %s to %s", server, uri, filepath)
        except IOError:
            # If move fails, check retry count before re-queuing
            if retry_count < MAX_RETRY_ATTEMPTS:
                retry_count += 1
                logging.warning("Failed to move %s. Adding to end of queue to retry later (attempt %d/%d).",
                               uri, retry_count, MAX_RETRY_ATTEMPTS)
                self.queue.put([server, percent, uri, retry_count])
            else:
                logging.error("Failed to move %s after %d attempts. Giving up on this file.",
                             uri, MAX_RETRY_ATTEMPTS)
        except:
            logging.warning("Failed to move %s.", uri)
