#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
nohup bash /home/helioviewer/setup_files/scripts/startup.sh > /tmp/startup_output &
timeout 90s bash -c 'until [[ "$(tail -n 1 /tmp/startup_output)" == "Container up and running" ]]; do
    echo waiting for container to be ready
    sleep 1
done'