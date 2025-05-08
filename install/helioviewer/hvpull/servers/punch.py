"""PUNCH DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class PUNCHDataServer(DataServer):
    def __init__(self):
        # TODO: Need to update to actual punch source when it's available.
        DataServer.__init__(self, "/tmp/incoming/", "PUNCH")
        self.pause = datetime.timedelta(minutes=30)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = [os.path.join(self.uri)]
        return dirs

    def get_datetime_from_file(self, filename):
        fname = os.path.basename(filename)
        datestr = fname[13:27]
        return datetime.datetime.strptime(datestr, '%Y%m%d%H%M%S')
