#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
cd /home/helioviewer/setup_files/scripts/
./startup.sh > startup_log &
sleep 60