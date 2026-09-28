import os
from helioviewer.hvpull.servers import DataServer

class SpiceDataServer(DataServer):
    """Solar Orbiter/SPICE Datasource definition"""
    def __init__(self):
        """Defines the root directory where SPICE JP2 files are kept."""
        spice_data_path = os.environ.get('SPICE_DATA_PATH')
        if not spice_data_path:
            raise ValueError("SPICE_DATA_PATH must be set to the SPICE JP2 root directory")

        DataServer.__init__(self, spice_data_path, "SPICE")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        return [os.path.join(self.uri, date) for date in self.get_dates(start_date, end_date)]
