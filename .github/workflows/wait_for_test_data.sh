#!/bin/bash
for i in {1..12}; do
    docker exec -t helioviewer-cli-1 ls /tmp/jp2/LASCO-C2/2023/12/01/white-light/
    if [ $? -eq 0 ]; then
    exit 0
    fi
    sleep 10
done
exit 1