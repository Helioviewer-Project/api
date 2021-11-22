#!/bin/bash

awk -F, '{ system("python3 /var/www/api.helioviewer.org/install/downloader.py -s" $1 " -e " $2 " -d jsoc -l /mnt/ssd/data/incoming/hvpull/log/sdo-backfill-from-gaps.log; sleep 1;") }' < /var/www/api.helioviewer.org/scripts/hv_stats/AIA_data_gaps_test.csv
