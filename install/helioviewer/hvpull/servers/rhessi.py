"""RHESSI DataServer"""
from helioviewer.hvpull.servers import DataServer
import datetime
import os


class RHESSIDataServer(DataServer):
    def __init__(self):
        """This assumes that SOHO jp2 files are calculated locally.  They are
        then copied over to a directory on the main Helioviewer server, from
        which it can be picked up by the ingestion services.  Note that
        a full path is required to specify the location of the data."""
        DataServer.__init__(self, "https://hesperia.gsfc.nasa.gov/~kim/rhessi_helioviewer/hv_input_files/rhessiwrite/v0.8/jp2/RHESSI/", "RHESSI")
        self.pause = datetime.timedelta(seconds=60)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []
        bands = ["3-6keV", "6-12keV", "12-25keV", "25-50keV", "50-100keV", "100-300keV"]
        for date in self.get_dates(start_date, end_date):
            for energy_band in bands:
                dirs.append(os.path.join(self.uri, date, energy_band))
        return dirs
