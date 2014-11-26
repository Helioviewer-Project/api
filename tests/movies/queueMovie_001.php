<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/Movies.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';

$params = array(
    'action'       => 'queueMovie',
    'startTime'    => '2014-01-01T00:00:00.000Z',
    'endTime'      => '2014-02-03T04:05:06.789Z',
    'layers'       => '[SDO,AIA,171,1,100],[SDO,AIA,193,1,50]',
    'events'       => '[AR,HMI_SHARP;SPoCA,1],[CH,all,1]',
    'eventsLabels' => 'false',
    'imageScale'   => '2.4204409',
    'x0'           => '0',
    'y0'           => '0',
    'width'        => '1920',
    'height'       => '1080'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$movies = new Module_Movies($params);

echo "\n\nOutputting evidence that input was accepted:\n";
echo '$movies => ';
var_dump($movies);

echo "\n\nExecuting API call:\n";
echo '$movies->execute()'."\n";

echo "\nActual Test Output:\n";
$movies->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>