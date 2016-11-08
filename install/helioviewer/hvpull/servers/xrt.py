"""XRT DataServer definition"""
import os
import sys
import datetime
from helioviewer.hvpull.servers import DataServer

class XRTDataServer(DataServer):
    """XRT Harverd Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at XRT."""
        DataServer.__init__(self, "http://xrt.cfa.harvard.edu/jp2", "XRT")
        self.pause = datetime.timedelta(minutes=15)
        
    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        
        hours = [
            "H0000", "H0100", "H0200", "H0300", "H0400", "H0500", "H0600", "H0700", "H0800", "H0900", 
            "H1000", "H1100", "H1200", "H1300", "H1400", "H1500", "H1600", "H1700", "H1800", "H1900", 
            "H2000", "H2100", "H2200", "H2300" 
        ]
        
        for date in self.get_dates((start_date - datetime.timedelta(days=21)), end_date):
            for hour in hours:
                dirs.append(os.path.join(self.uri, date, str(hour))) 

        return dirs
