#!/bin/bash
set -x
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
cat /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
echo '' | /home/helioviewer/setup_files/scripts/startup.sh