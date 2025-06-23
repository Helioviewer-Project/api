"""RHESSI DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os


class HVRHESSIDataServer(DataServer):
    def __init__(self):
        """This assumes that SOHO jp2 files are calculated locally.  They are
        then copied over to a directory on the main Helioviewer server, from
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "https://helioviewer.org/jp2/RHESSI/", "RHESSI")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        methods = ["Back_Projection", "Clean", "Clean59", "MEM_GE", "VIS_CS", "VIS_FWDFIT"]
        for date in self.get_dates(start_date, end_date):
            for method in methods:
                dirs.append(os.path.join(self.uri, date, method))
        return dirs
