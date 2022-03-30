#!/usr/bin/env bash
# This script is use to perform various status checks on helioviewer.org
# and update the XML feed if a problem is detected.
# this script is expected to run on a cron job.

# Exit the script if the lockfile exists. The lockfile is created once
# an error is detected
lockfile=feed.lock
if [ -f $lockfile ]
then
    exit
fi

# Set HV_FEED_HOST if it hasn't been set by the environment
if [ -z ${HV_FEED_HOST} ]
then
    HV_FEED_HOST=https://api.helioviewer.org
fi

# Attempt to load helioviewer.org via curl
curl -s $HV_FEED_HOST --output helioviewer.html
# If curl returns a non-zero exit code
if [ ! $? = 0 ]
then
    python3 gen_feed.py ../../docroot/status.xml -t "Helioviewer is unreachable." -d "Helioviewer health check failed to reach $HV_FEED_HOST"
    touch $lockfile
fi
rm helioviewer.html

# Check if image sources are lagging behind schedule
result=`python3 check_status.py`
if [ ! -z "$result" ]
then
    python3 gen_feed.py ../../docroot/status.xml -t "Helioviewer images are behind schedule." -d "$result"
    touch $lockfile
fi
