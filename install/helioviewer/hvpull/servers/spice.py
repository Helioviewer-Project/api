"""Solar Orbiter/SPICE DataServer definition."""

import datetime
import os
import re

from helioviewer.hvpull.servers import DataServer


class SpiceDataServer(DataServer):
    """Solar Orbiter/SPICE Datasource definition."""

    def __init__(self):
        """Defines the remote root directory where SPICE JP2 files are kept."""
        DataServer.__init__(
            self,
            "https://helioviewer.ias.u-psud.fr/jp2/SPICE/",
            "SPICE",
        )

        self.pause = datetime.timedelta(minutes=60)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files."""
        dirs = []

        channels = [
            "774",
            "103.19",
            "70.38",
            "78.77",
            "70.6",
            "97.25",
            "76.51",
            "97.7",
        ]

        for date in self.get_dates(start_date, end_date):
            for channel in channels:
                dirs.append(os.path.join(self.uri, date, channel))

        return dirs

    def get_datetime_from_file(self, filename):
        """Extract acquisition datetime from a SPICE JP2 filename."""
        url_filename = os.path.basename(filename)

        match = re.search(r"_(\d{8}T\d{6})_", url_filename)

        if not match:
            raise ValueError(
                f"Unable to extract SPICE datetime from filename: {url_filename}"
            )

        return datetime.datetime.strptime(
            match.group(1),
            "%Y%m%dT%H%M%S",
        )
