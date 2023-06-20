#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
nohup bash /home/helioviewer/setup_files/scripts/startup.sh > startup_output &
timeout 120s bash -c 'until [[ "$(tail -n 1 startup_output)" == "Container up and running" ]]; do
    sleep 1
    tail -n 1 startup_output
done'