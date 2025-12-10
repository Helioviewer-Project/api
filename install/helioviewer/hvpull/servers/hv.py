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
            # Recursively enumerate subdirectories starting from date URL
            dirs.extend(self._enumerate_subdirectories(date_url))

        return dirs

    def _enumerate_subdirectories(self, url):
        """Recursively enumerate subdirectories by querying the URL"""
        try:
            response = requests.get(url)
            response.raise_for_status()

            # Extract subdirectory links from HTML
            subdirs = self._parse_directory_links(response.content.decode('utf-8'))

            if not subdirs:
                # No subdirectories found, this is a leaf directory
                return [url]

            # Recursively enumerate each subdirectory
            all_dirs = []
            for subdir in subdirs:
                subdir_url = f"{url}/{subdir}"
                all_dirs.extend(self._enumerate_subdirectories(subdir_url))

            return all_dirs

        except requests.RequestException:
            # If we can't query the URL, return it as a leaf directory
            return [url]

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
