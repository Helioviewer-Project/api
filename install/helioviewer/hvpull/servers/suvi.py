import os
import datetime
from helioviewer.hvpull.servers import DataServer

class SUVIDataServer(DataServer):
    """GOES/SUVI Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "http://swhv.oma.be/jp2/SUVI/", "SUVI")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        wavelengths = [93, 131, 171, 195, 284, 303]

        dates = self.get_dates(start_date, end_date)
        months = self._get_months(dates)
        for month in months:
            for wavelength in wavelengths:
                # Special case: 195 and 303 have some files outside of the year folder.
                if (wavelength == 195 or wavelength == 303):
                    dirs.append(os.path.join(self.uri, str(wavelength)))

                # Besides those special cases above, the files follow a regular directory structure.
                dirs.append(os.path.join(self.uri, str(wavelength), month))

        return dirs

    def _get_months(self, dates):
        """
        Reduce dates into a list of year/month
        For example a list such as [2022/08/01, 2022/08/02] will turn into [2022/08]

        The SUVI directory structure doesn't have folders for days.
        """
        months = []
        for date in dates:
            # split the year/month/day into individual parts
            year, month, day = date.split('/')
            # Rebuild date with just the year/month
            year_month = year + '/' + month
            # Check if its already in our months array
            if (year_month not in months):
                # If not, then add it.
                months.append(year_month)
        # Return the collection of months without days.
        return months

