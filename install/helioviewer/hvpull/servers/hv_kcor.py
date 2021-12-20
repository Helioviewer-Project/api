"""KCor DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class HVKCORDataServer(DataServer):
    def __init__(self):
        """
        Pulling KCOR from helioviewer.org for mirroring purposes.
        """
        DataServer.__init__(self, "https://helioviewer.org/jp2/", "kcor")
        self.pause = datetime.timedelta(minutes=30)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            # KCOR
            dirs.append(os.path.join(self.uri, "KCor", date , "735" ))

        return dirs

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
