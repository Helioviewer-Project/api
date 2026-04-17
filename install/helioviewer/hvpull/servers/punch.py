"""PUNCH DataServer"""
from helioviewer.hvpull.servers import DataServer
from itertools import product,starmap
import datetime
import os

class PUNCHDataServer(DataServer):
    def __init__(self):
        # TODO: Need to update to actual punch source when it's available.
        DataServer.__init__(self, "https://umbra.nascom.nasa.gov/punch/L/3/", "PUNCH")
        self.pause = datetime.timedelta(minutes=30)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        top_level_folders = ["CAM","PAM"]

        dirs = starmap(
          lambda date, folder: os.path.join(self.uri, folder, date),
          product(self.get_dates(start_date, end_date), top_level_folders)
        )
        return list(dirs)

    def get_datetime_from_file(self, filename):
        fname = os.path.basename(filename)
        datestr = fname[13:27]
        return datetime.datetime.strptime(datestr, '%Y%m%d%H%M%S')
