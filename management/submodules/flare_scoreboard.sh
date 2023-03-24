#!/bin/sh
# Generates autoload files for the flare scoreboard submodule

set -e
# Initializes the flare scoreboard submodule
root_dir=$(git rev-parse --show-toplevel)
# Make sure submodules are initialized so we know the scoreboard files are there.
git submodule update --init --recursive
cd $root_dir/src/FlareScoreboard
# Install locked version of composer (2.5.5 2023-03-21)
curl -s -X GET https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer --output - | php -- --quiet
# Generate autoloader
./composer.phar dump-autoload
# Remove composer
rm composer.phar