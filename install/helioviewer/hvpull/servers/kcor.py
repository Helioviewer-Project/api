"""KCor DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os

class KCORDataServer(DataServer):
    def __init__(self):
        """This assumes that KCor jp2 files are calculated locally.  They are 
        then copied over to a directory on the main Helioviewer server, from 
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "https://download.hao.ucar.edu/jp2/", "kcor")
        self.pause = datetime.timedelta(minutes=30)
        
    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        
        for date in self.get_dates(start_date, end_date):
            # KCOR
            dirs.append(os.path.join(self.uri, "kcor", date , "white-light-pB" ))
                
        return dirs
    
    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
