"""RHESSI DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os


class RHESSIDataServer(DataServer):
    def __init__(self):
        """This assumes that SOHO jp2 files are calculated locally.  They are
        then copied over to a directory on the main Helioviewer server, from
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "~/api/jp2_ingest", "RHESSI")
        self.pause = datetime.timedelta(seconds=1)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        return [self.uri]

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.now() - datetime.timedelta(days=3)
