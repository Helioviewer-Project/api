import os
import datetime
from helioviewer.hvpull.servers import DataServer

class SUVIDataServer(DataServer):
    """GOES/SUVI Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at ROB."""
        DataServer.__init__(self, "http://swhv.oma.be/jp2/", "SUVI")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        wavelengths = ["fe094", "fe131", "fe171", "fe195", "fe284", "he303"]
        # prefix each type with suvi_ to create the folder name
        wavelengths = list(map(lambda t: f"suvi_{t}", wavelengths))

        dates = self.get_dates(start_date, end_date)
        for date in dates:
            for wavelength in wavelengths:
                dirs.append(os.path.join(self.uri, wavelength, date))

        return dirs
