import os
from datetime import datetime

from helioviewer.hvpull.servers import DataServer

class PHIDataServer(DataServer):
    def __init__(self):
        """This assumes that SOLO jp2 files are stored locally.
        Note that a full path is required to specify the location of the data."""
        DataServer.__init__(self, "/tmp/phi", "PHI")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        return [self.uri]

    def get_datetime_from_file(self, filename):

        t = os.path.basename(filename)[21:36]
        return datetime.strptime(t, "%Y%m%dT%H%M%S")
