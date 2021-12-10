"""HelioViewer SOHO Cache"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class HVSOHODataServer(DataServer):
    def __init__(self):
        """
        This source pulls directly from helioviewer.org. It is meant to be used
        for mirrors rather than the main server itself.
        """
        DataServer.__init__(self, "https://helioviewer.org/jp2/", "SOHO")
        self.pause = datetime.timedelta(minutes=30)
        
    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        
        eit_wavelengths = [171, 195, 284, 304]
        lasco_detectors = ["C2", "C3"]
        
        for date in self.get_dates(start_date, end_date):
            # EIT
            for meas in eit_wavelengths:
                dirs.append(os.path.join(self.uri, "EIT", date, str(meas)))
            
            # LASCO
            for detector in lasco_detectors:
                dirs.append(os.path.join(self.uri, "LASCO-"+detector, date, "white-light"))
                
        return dirs
    
    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
