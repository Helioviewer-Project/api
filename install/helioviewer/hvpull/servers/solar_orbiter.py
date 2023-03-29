import os
import datetime
from helioviewer.hvpull.servers import DataServer

class SolarOrbiterDataServer(DataServer):
    """SolarOrbiter Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "https://www.sidc.be/EUI/data/releases/202301_release_6.0/L3/", "Solar_Orbiter")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        for date in self.get_dates(start_date, end_date):
            dirs.append(os.path.join(self.uri, date))

        return dirs

    def get_datetime_from_file(self, filename):
        url_filename = os.path.basename(filename)
        url_datetime = url_filename[-26:-11]
        return datetime.datetime.strptime(url_datetime, '%Y%m%dT%H%M%S')

