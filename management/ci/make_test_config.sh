#!/bin/bash
sed "s:/var/www-api/docroot/cache:/home/helioviewer/helioviewer.org/cache:" settings/Config.Example.ini | \
sed "s:/var/www-api/docroot:/home/helioviewer/api.helioviewer.org/docroot:" | \
sed "s-http://localhost-http://127.0.0.1-"
