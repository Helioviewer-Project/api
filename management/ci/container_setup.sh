#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
nohup bash /home/helioviewer/setup_files/scripts/startup.sh > /tmp/startup_output &
sleep 90