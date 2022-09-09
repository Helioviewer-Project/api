#!/bin/bash
cmd=$0
usage() {
	echo "Usage: "
	echo "  $cmd <start date> <end date>"
	echo
	echo "Updates the statistics used to"
	echo "create the Image & Events timeline"
}

# Parse arguments with getopt
args=`getopt -o abch -l help -- $*`
# If no arguments were given, show usage
if [[ $? -ne 0 ]]
then
	usage
	exit 2
fi

# Iterate over options.
set -- $args # Writes $args into $1,$2,$3,etc
while :; do
	case $1 in
		-h|--help)
			usage
			exit 0;;
		--)
			shift
			break;;
	esac

	# Shifts positional params left, i.e. $2 -> $1, $3 -> $2, etc
	shift
done

# Confirm that we have a date range
if [[ $1 == "" ]] || [[ $2 == "" ]]
then
	echo Dates not provided
	usage
	exit 2
fi

# There are strange things with quotes happening, and passing them along
bash -c "php ../events/import_events.php -u $1 $2"

