"""IRIS DataServer definition"""
import os
import re
import requests
from datetime import datetime
from helioviewer.hvpull.servers import DataServer

class IrisFolder:
    def __init__(self, folder: str, timestamp: datetime):
        self.folder = folder
        self.timestamp = timestamp

class IRISDataServer(DataServer):
    """IRIS Datasource definition"""
    def __init__(self):
        """Defines the root directory of where the data is kept at LMSAL."""
        DataServer.__init__(self, "https://www.lmsal.com/cruiser/observatory/iris_jp2k/data/", "IRIS")
        self.pause = datetime.timedelta(minutes=60)

    def compute_directories(self, start_date, end_date):
        """Computes a list of remote directories expected to contain files"""
        response = requests.get(self.uri)
        # This URI doesn't follow the typical organization, so instead we need to parse all the folders given by the web directory
        folders = self._get_folders(response.content.decode('utf-8'))
        # After getting the folders, extract the folders with the relevant dates
        relevant_folders = filter(lambda f: f.timestamp.date() >= start_date.date() and f.timestamp.date() <= end_date.date(), folders)
        # Return the folder URLs
        return [os.path.join(self.uri, x.folder) for x in relevant_folders]

    def _get_folders(self, html: str) -> [IrisFolder]:
        """
        Parses the given html content and returns a list of folders organized by timestamp
        """
        # Extract all links from the HTML
        matches = re.findall('href="(.*?)"', html)
        folders = []
        for folder in matches:
            if folder == '/cruiser/observatory/iris_jp2k/':
                continue
            timestamp = datetime.strptime(folder[:8], "%Y%m%d")
            folders.append(IrisFolder(folder, timestamp))
        folders.sort(key=lambda f: f.timestamp)
        return folders

    def get_datetime_from_file(self, filename):
        url_filename = os.path.basename(filename)
        url_datetime = url_filename[11:26]
        return datetime.strptime(url_datetime, '%Y%m%d_%H%M%S')

    def get_starttime(self):
        """Default start time to use when retrieving data"""
        return datetime.datetime.utcnow() - datetime.timedelta(days=3)
