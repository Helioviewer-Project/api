<?php
require_once '../config.php';

/**
 * prints script usage information.
 * Optionally exits the program as well
 * @param $name String Name of the script to print on the cmd line
 */
function print_usage($name) {
	echo "Usage:
   $name <start date> <end date>

Updates the image timeline for the given time range.
\n";
}

function parse_args($argv) {
	// Use getopt to parse command line options
	$opts = getopt('h', ['help'], $rest_index);
	if ($opts) {
		foreach ($opts as $opt => $value) {
			switch ($opt) {
				// Check if '-h' or '--help' was given
				case "h":
				case "help":
					print_usage($argv[0]);
					exit(0);
			}
		}
	}

	// Get the positional parameters from the command line args
	$args = array_slice($argv, $rest_index);
	// Make sure there are 2 arguments
	$count = count($args);

	if ($count != 2) {
		echo "Error: Unexpected number of arguments given\n\n";
		print_usage($argv[0], true);
		exit(1);
	}

	// Parse dates into unix time and make sure times conform to UTC time.
	$tz = new DateTimeZone("UTC");
	$start = new DateTimeImmutable($args[0], $tz);
	$end = new DateTimeImmutable($args[1], $tz);
	$format = 'Y-m-d H:i:s';

	$result = [
		'start' => $start->format($format),
		'end' => $end->format($format)
	];
	return $result;
}

function updateTimeline($start, $end) {
	require_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
	echo "Updating data coverage timeline\n";
	$stats = new Database_Statistics();
	$success = $stats->updateImageCoverageOverRange($start, $end);
	if ($success) {
		echo "Successfully updated image timeline\n";
	} else {
		echo "Failed to update timeline.\n";
		echo "See logs for details.\n";
	}
}

$args = parse_args($argv);
$start = $args['start'];
$end = $args['end'];
updateTimeline($start, $end);

