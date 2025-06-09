"""CCOR2 DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime

class CCOR2DataServer(DataServer):
    def __init__(self):
        DataServer.__init__(self, "https://services.swpc.noaa.gov/experimental/products/swfol1/", "CCOR")
        self.pause = datetime.timedelta(minutes=15)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        ccor_detectors = ["ccor-1", "ccor-2"]

        # CCOR
        for detector in ccor_detectors:
            dirs.append(f"{self.uri}/{detector}/jp2")

        return dirs

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=7)
