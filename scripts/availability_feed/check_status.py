"""
This script will query HV_FEED_HOST/v2/getStatus/ in order to determine
how old the latest images are.

If certain thresholds specified in this file are met, then a message
will be printed which indicates which image source is behind schedule.
"""

from gen_feed import get_host
from configparser import ConfigParser
import requests

# Load thresholds from the config file
config = ConfigParser()
# First read the example ini, this is always expected to exist
config.read('thresholds.example.ini')
# Then override any of those values with the user created ini file.
# Note, if this file doesn't exist, then this will have no effect
config.read('thresholds.ini')
# Create the threshold object.
thresholds = config['thresholds']

def _get_threshold(key):
    return float(thresholds[key])

def query_status_api():
    host = get_host()
    api_endpoint = host + "/v2/getStatus/"
    resp = requests.get(api_endpoint)
    return resp.json()

def check_for_lag(source: str, threshold_seconds: float, seconds_behind: int):
    if (seconds_behind > threshold_seconds):
        threshold_hours = threshold_seconds / 60 / 60
        hours_behind = seconds_behind / 60 / 60
        print("%s image are behind by %0.2f hours. Alert threshold is %0.2f hours." % (source, hours_behind, threshold_hours))

def check_status(status):
    aia_seconds_behind = status['AIA']['secondsBehind']
    check_for_lag("AIA/HMI", _get_threshold('aia'), aia_seconds_behind)

    # this covers SOHO
    lasco_seconds_behind = status['LASCO']['secondsBehind']
    check_for_lag("LASCO", _get_threshold('lasco'), lasco_seconds_behind)

    # this coveres STEREO
    secchi_seconds_behind = status['SECCHI']['secondsBehind']
    check_for_lag("STEREO Source", _get_threshold('secchi'), secchi_seconds_behind)
    # SWAP
    swap_seconds_behind = status['SWAP']['secondsBehind']
    check_for_lag("SWAP", _get_threshold('swap'), swap_seconds_behind)
    pass

if __name__ == "__main__":
    status_json = query_status_api()
    check_status(status_json)
