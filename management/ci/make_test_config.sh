#!/bin/bash
cmd=$0
usage() {
    echo "Usage: "
    echo "  $cmd <Config.ini path> <Private.php path>"
    echo
    echo "Creates a Config.ini and Private.php to use for testing"
    echo "The provided paths are where the test configuration files are written"
}

if [[ "$1" == "-h" ]] || [[ "$1" == "--help" ]] || [[ $# -ne 2 ]]; then
    usage
    exit 0
fi

CONFIG_INI=$1
PRIVATE_PHP=$2

# Make Config.ini
sed "s:/var/www-api/docroot/cache:$PWD/cache:" settings/Config.Example.ini | \
sed "s:/var/www-api/docroot:$PWD/docroot:" | \
sed "s-http://localhost-http://127.0.0.1:81-" > $CONFIG_INI

# Make Private.php
sed "s/localhost/127.0.0.1/" settings/Private.Example.php > $PRIVATE_PHP
