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
                split = date.split('/')
                # Some dates older than 2024 aren't in day folders, but all files are
                # in the month folder, so make sure that gets scanned.
                dirs.append(os.path.join(self.uri, wavelength, split[0], split[1]))

        # list(set(x)) removes duplicates
        deduped = list(set(dirs))
        deduped.sort()
        return deduped
