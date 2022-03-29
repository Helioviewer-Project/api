"""
This script will query HV_FEED_HOST/v2/getStatus/ in order to determine
how old the latest images are.

If certain thresholds specified in this file are met, then a message
will be printed which indicates which image source is behind schedule.
"""

from gen_feed import get_host
import requests

# If AIA seconds behind is greater than this value, then it is a problem.
# AIA should not be more than 2 hours behind since helioviewer.org pulls
# every hour.
AIA_LAG_THRESHOLD_S = 7200

def query_status_api():
    host = get_host()
    api_endpoint = host + "/v2/getStatus/"
    resp = requests.get(api_endpoint)
    return resp.json()

def check_status(status):
    aia_seconds_behind = status['AIA']['secondsBehind']
    if aia_seconds_behind > AIA_LAG_THRESHOLD_S:
        print("AIA/HMI images are not current.")

    # TODO: Set a threshold for lasco
    # soho_seconds_behind = status['LASCO']['secondsBehind']
    pass

if __name__ == "__main__":
    status_json = query_status_api()
    check_status(status_json)
