"""TRACE DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os


class TRACEDataServer(DataServer):
    def __init__(self):
        """This assumes that TRACE jp2 files are calculated locally.  They are
        then copied over to a directory on the main Helioviewer server, from
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "/home/ireland/incoming/trace_incoming/v0.8/jp2", "TRACE")
        self.pause = datetime.timedelta(minutes=30)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        measurements = ["WL", "171", "195", "284", "1216", "1550", "1600", "1700"]

        for date in self.get_dates(start_date, end_date):
            for measurement in measurements:
                dirs.append(os.path.join(self.uri, "TRACE", date, measurement))

        return dirs

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
