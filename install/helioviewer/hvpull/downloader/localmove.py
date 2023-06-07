"""moves files from one location on the local file system to another"""
import os
import logging
import threading
import time
import shutil
from downloader_interface import Downloader

class LocalFileMove(Downloader):
    def __init__(self, incoming, queue):
        """Creates a new LocalFileMover"""
        super().__init__(incoming, queue)

        # The fields below are set by the Downloader parent class
        # self.incoming
        # self.queue

    def process(self, item):
        """Downloads the file at the specified URI"""
        #ValueError: too many values to unpack
        server, percent, uri = item

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
            # If download fails, add back into queue and try again later
            logging.warning("Failed to move %s. Adding to end of queue to retry later.", uri)
            self.queue.put([server, percent, uri])
        except:
            logging.warning("Failed to move %s.", uri)
