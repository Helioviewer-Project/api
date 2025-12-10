import os
import re
import requests
from helioviewer.hvpull.servers import DataServer

class HvDataServer(DataServer):
    def __init__(self):
        # Get the HV_DATA_PATH environment variable, defaulting to helioviewer.org if not set
        hv_data_path = os.environ.get('HV_DATA_PATH', 'https://helioviewer.org/jp2')
        DataServer.__init__(self, hv_data_path, "HV")

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        dirs = []

        # Start with date directories
        for date in self.get_dates(start_date, end_date):
            date_url = os.path.join(self.uri, date)

            # Query the URL to find subdirectories
            try:
                response = requests.get(date_url)
                response.raise_for_status()

                # Extract subdirectory links from HTML
                subdirs = self._parse_directory_links(response.content.decode('utf-8'))

                if subdirs:
                    # Add each subdirectory with date_url as prefix
                    for subdir in subdirs:
                        dirs.append(f"{date_url}/{subdir}")
                else:
                    # No subdirectories found, add the date URL itself
                    dirs.append(date_url)

            except requests.RequestException:
                # If we can't query the URL, add it as-is
                dirs.append(date_url)

        return dirs

    def _parse_directory_links(self, html):
        """Parse HTML content and extract directory links"""
        # Match href links
        matches = re.findall(r'href="([^"]+)"', html)

        dirs = []
        for match in matches:
            # Skip parent directory links, absolute URLs, and paths starting with /
            if match in ['/', '../', '..', '/..'] or match.startswith('http') or match.startswith('/'):
                continue

            # Only keep directory links (ending with /)
            if match.endswith('/'):
                # Remove trailing slash for consistency
                dir_name = match.rstrip('/')
                if dir_name:
                    dirs.append(dir_name)

        return dirs
