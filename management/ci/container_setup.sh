#!/bin/sh
ln -s $PWD /home/helioviewer/api.helioviewer.org
chown -R helioviewer:helioviewer .
su helioviewer -c "echo | /home/helioviewer/setup_files/scripts/startup.sh"