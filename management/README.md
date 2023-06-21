# Helioviewer Management Scripts
This folder contains scripts related to Heliovewer data management.
There are many scripts in the `scripts` directory that don't have much useful documentation around them.
New scripts should be added to this directory and adhere to the following standards.

## Script Requirements
Scripts should implement the common standard
[posix utility interface](https://pubs.opengroup.org/onlinepubs/9699919799/basedefs/V1_chap12.html).
The short version of the standard is that scripts should use the common
CLI interface you see with most command line programs.
- Options in the form `-a arg` or `--someoption arg`
- A help message that appears for `-h` and `--help` that contains a brief description of the script and how to use it.

These are generally easy to implement, and you can use other scripts here as examples.
Here's a quick reference for libraries used to help implement the standard interface:
- For python, use [argparse](https://docs.python.org/3/library/argparse.html)
- For php, use php's built in [getopt](https://www.php.net/manual/en/function.getopt.php)
- For bash, use [getopt](https://www.man7.org/linux/man-pages/man1/getopt.1.html)

## Directory Requirements
Each subdirectory should have a README containing a description of the type of scripts that should be placed in that directory and a listing of its current contents with brief descriptions.
This gives an overview of what scripts are available at a glance.

# Top Level Directory Listing
## ci
Scripts related to Continuous Integration tests

## events
Contains scripts related to features and events management.

## statistics
Contains scripts related to data coverage and usage statistics.

## config.php
Import into php scripts to load the configured Config.ini.
This is a compatibility layer so that every script doesn't need to search '../../../' to load the main Config.php.
They just need to include this one.

