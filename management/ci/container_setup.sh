#!/bin/sh
ln -s $PWD /home/helioviewer/api.helioviewer.org
chown -R helioviewer:helioviewer .
su helioviewer -c "nohup /home/helioviewer/setup_files/scripts/startup.sh &"
sleep 60