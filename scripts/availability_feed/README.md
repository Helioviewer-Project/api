# Helioviewer Availability Service
The Helioviewer Availability Service (HAS) is an RSS feed
published to https://api.helioviewer.org/status.xml.
The goal of this service is to provide a feed of
uptime/downtime notifications for various Helioviewer
Services (JPIP & Web Application)

# How It Works
The source for this lives in the API repository in
`repo_root/scripts/availability_feed`

All scripts mentioned below are expected to be run this `availability_feed`
directory. The scripts create some local temporary files and reference other
parts of the repository via relative paths.

## health_check.sh
`health_check.sh` is a bash script that is intended to be run
as a cron job. It performs several health checks in order to
confirm that helioviewer services are available. Currently it
performs the following checks:

- Check to see that accessing https://helioviewer.org returns a web page.
- Checks https://api.helioviewer.org/v2/getStatus/ to verify that data being downloaded isn't lagging further behind than expected.

When either of the above checks fail, then `gen_feed.py` is called
to update the RSS file with a new status.

In order to keep feed updates to only new issues, a file is created called
`feed.lock` which prevents this script from running again until the lock
file is removed. This gives the team the ability to go fix the reported problem
instead of constantly updating the feed reporting a problem.

## check_status.py
`check_status.py` is used to query helioviewer's status API which tells how old
the latest available images are. If the latest images are older than a certain
time, then it is likely because some downloader service has stopped, and we
should be made aware of this.

The script will return a message indicating which image source is behind.
This message can be dropped directly into the RSS feed.

## helioviewer_is_back.sh
`helioviewer_is_back.sh` is used to quickly and easily update the feed to
report that the previous problem has been resolved. It pushes a generic message
to the feed that states the previous error has been resolved. It also removes
`feed.lock` so once a problem is fixed, we can simply execte `helioviewer_is_back.sh`
and the health check script can continue running.

## gen_feed.py
`gen_feed.py` is the script use to generate the RSS Feed file
`status.xml`

Usage:
```bash
python gen_feed.py /path/to/status.xml "Service Up/Down" "Description of what went wrong (or right)"
```

This simple usage will create a new entry in the RSS feed.
The timestamp for the entry will be the time the command was run.
The first command line argument is the status.xml file to be created or updated.
The next is the `<title>` of the RSS entry
Lastly, the third parameter is for the `<description>` tag.

### Important Notes
`gen_feed.py` uses the feedgen python library. This library doesn't have
built in support for loading existing feeds, so the workaround for this
is to save the feed as a pickle file. The pickle file is created
in the directory the command runs in. Therefore it is important to always
run the command from the same directory in order for this to properly
update your feed. If you don't do this, then a new feed will be created
every time you run the command and no status history will be saved.

### Re-Hosting gen_feed.py
If you're running a mirror of helioviewer, you'll want to change the link
that is listed in the RSS feed. By default, the feed links back to
https://api.helioviewer.org

You may set the environment variable `HV_FEED_HOST=https://your.host`
in order to update the backlink in the RSS feed.
