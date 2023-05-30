"""IRIS DataServer definition"""
import os
import re
from datetime import datetime
from helioviewer.hvpull.servers import DataServer
from helioviewer.hvpull.servers.iris import IRISDataServer

class HvIRISDataServer(IRISDataServer):
    """IRIS Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept on Helioviewer."""
        DataServer.__init__(self, "https://api.helioviewer.org/jp2/", "IRIS")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        wavelengths = [1330, 1400, 2796, 2832]

        for date in self.get_dates(start_date, end_date):
            for meas in wavelengths:
                dirs.append(os.path.join(self.uri, "SJI", date, str(meas)))

        return dirs