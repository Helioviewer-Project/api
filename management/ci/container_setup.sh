#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
cd /home/helioviewer/setup_files/scripts/
./startup.sh > startup_log &
until [[ "$(tail -n 1 startup_log)" == "Container up and running" ]]; do
    sleep 1
done