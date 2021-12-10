"""HelioViewer STEREO Cache"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class HVSTEREODataServer(DataServer):
    def __init__(self):
        """
        This source pulls directly from helioviewer.org. It is meant to be used
        for mirrors rather than the main server itself.
        """
        DataServer.__init__(self, "https://helioviewer.org/jp2/", "STEREO")
        self.pause = datetime.timedelta(hours=3)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        
        euvi_wavelengths = [171, 195, 284, 304]
        cor_detectors = ["1-A", "1-B", "2-A", "2-B"]
        
        for date in self.get_dates(start_date, end_date):
            # EUVI
            for meas in euvi_wavelengths:
                dirs.append(os.path.join(self.uri, "EUVI-A", date, str(meas)))
                dirs.append(os.path.join(self.uri, "EUVI-B", date, str(meas)))
            
            # COR
            for detector in cor_detectors:
                dirs.append(os.path.join(self.uri, "COR"+detector, date, "white-light"))
        
        return dirs
        
    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=7)