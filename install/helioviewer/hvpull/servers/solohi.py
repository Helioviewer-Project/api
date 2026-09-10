import os
import datetime
from helioviewer.hvpull.servers import DataServer

class SoloHIDataServer(DataServer):
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "https://solohi.nrl.navy.mil/so_data/MOS_jp2/", "SoloHI")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date, "%Y%m%d"):
            dirs.append(os.path.join(self.uri, date))

        return dirs

    def get_datetime_from_file(self, filename):
        url_filename = os.path.basename(filename)
        url_datetime = url_filename[-23:-8]
        return datetime.datetime.strptime(url_datetime, '%Y%m%d_%H%M%S')

