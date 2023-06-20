#!/bin/bash
echo "root ALL=(ALL) NOPASSWD: ALL" >> /etc/sudoers
ln -s $PWD /home/helioviewer/api.helioviewer.org
cd /home/helioviewer/setup_files/scripts/
sed "s:read::" startup.sh > startup2.sh
bash startup2.sh