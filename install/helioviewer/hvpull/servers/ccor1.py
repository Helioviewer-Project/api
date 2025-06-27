"""CCOR1 DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime

class CCOR1DataServer(DataServer):
    def __init__(self):
        DataServer.__init__(self, "https://services.swpc.noaa.gov/products/ccor1/jp2/", "CCOR1");
        self.pause = datetime.timedelta(minutes=15)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        return [f"{self.uri}"]

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=7)
