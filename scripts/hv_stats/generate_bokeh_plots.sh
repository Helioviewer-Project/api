#!/bin/bash
echo "##### Starting Bokeh Plot Generation Script #####"
date
/usr/bin/python3 hv_stats.py
echo "Copying Files to Web Directory api.helioviewer.org/docroot/statistics/bokeh/"
cp -r Jhv_movies histograms hv_movies hv_screenshots hv_student coverages embed_service service_usage /var/www/api.helioviewer.org/docroot/statistics/bokeh/
date
echo "Done!"
echo "#################################################"
