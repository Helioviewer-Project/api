#!/bin/sh
ln -s $PWD /home/helioviewer/api.helioviewer.org
chown -R helioviewer:helioviewer .
su helioviewer -c "bash -c \"/home/helioviewer/setup_files/scripts/startup.sh > startup.log &\""
sleep 30