"""Local DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class LocalDataServer(DataServer):
    def __init__(self):
        """This assumes that local jp2 files are stored in a directory
        specified by the LOCAL_DATA_DIR environment variable. Files are
        expected to be organized in subdirectories that can be picked up
        by the ingestion services. Note that a full path is required to
        specify the location of the data."""
        local_data_dir = os.environ.get('LOCAL_DATA_DIR')
        if not local_data_dir:
            raise ValueError("LOCAL_DATA_DIR environment variable is not set")
        DataServer.__init__(self, local_data_dir, "Local")
        self.pause = datetime.timedelta(minutes=30)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files
        by recursively finding all folders and subfolders in LOCAL_DATA_DIR"""
        dirs = []

        # Recursively walk through all directories in LOCAL_DATA_DIR
        for root, dirnames, filenames in os.walk(self.uri):
            # Add each subdirectory found
            for dirname in dirnames:
                dirs.append(os.path.join(root, dirname))
            # Also add the root directory itself if it's not already the base uri
            if root != self.uri:
                dirs.append(root)

        # Add the base directory itself
        if self.uri not in dirs:
            dirs.append(self.uri)

        return dirs

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow()
