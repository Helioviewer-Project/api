# Data
This directory contains scripts related to image data management.

# Listing
Directory Listing

## get_jp2_info.py
Parses a given jp2 image and prints out important metadata needed for adding new image sources.

## refill.py
Use this script when data needs to be deleted and re-downloaded.
This may happen when the upstream images are regenerated.
This script will delete the rows in the database, backup the files to be deleted and move them to /tmp/refill, and then execute downloader.py to redownload images over the given time range.