<?php
require_once '../config.php';

/**
 * prints script usage information.
 * Optionally exits the program as well
 * @param $name String Name of the script to print on the cmd line
 */
function print_usage($name) {
	echo "Usage:
   $name [-u|e|s] [--update-only|hek|scoreboard] <start date> <end date>

Imports HEK events into the Helioviewer Database.
You must specify the date range for the events to import.

Optional Arguments:
  -h                 Show this help message
  -u,--update-only   Update statistics only, don't download any events
  -e,--hek           Download events from HEK
  -s,--scoreboard    Download events from the CCMC Flare Scoreboard
\n";
}

function parse_args($argv) {
	// Store default values for optional parameters
	$flags = array(
		"update-only" => false,
		"hek" => false,
		"scoreboard" => false
	);
	// Use getopt to parse command line options
	$opts = getopt('hues', ['help', 'update-only', 'hek', 'scoreboard'], $rest_index);
	if ($opts) {
		foreach ($opts as $opt => $value) {
			switch ($opt) {
				// Check if '-h' or '--help' was given
				case "h":
				case "help":
					print_usage($argv[0]);
					exit(0);

				// Check if '-u' or '--update-only' was given
				case "u":
				case "update-only":
					$flags['update-only'] = true;
					break;

				case "e":
				case "hek":
					$flags['hek'] = true;
					break;

				case "s":
				case "scoreboard":
					$flags['scoreboard'] = true;
					break;
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
	$result = array_merge($result, $flags);
	return $result;
}

function downloadEvents($start, $end, $flags) {
	require_once HV_ROOT_DIR.'/../src/Event/HEKAdapter.php';
	echo "Starting HEK import over $start to $end". "\n";
	// Query the HEK
	$hek = new Event_HEKAdapter();
	if ($flags['hek']) {
		$hek->importEventsOverRange($start, $end);
	}
	if ($flags['scoreboard']) {
		$hek->importScoreboardEvents($start, $end);
	}
}

function updateTimeline($start, $end) {
	require_once HV_ROOT_DIR.'/../src/Database/Statistics.php';
	echo "Updating event coverage timeline\n";
	$stats = new Database_Statistics();
	$json_result = $stats->updateDataCoverageOverRange($start, $end);
	$result = json_decode($json_result);
	if ($result->result) {
		echo "Successfully updated timeline\n";
	} else {
		echo "Failed to update timeline.\n";
		echo "See logs for details.\n";
	}
}

$args = parse_args($argv);

$start = $args['start'];
$end = $args['end'];

if (!$args['update-only']) {
	downloadEvents($start, $end, $args);
}
updateTimeline($start, $end);

