<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/Movies.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';

$params = array(
    'action'       => 'queueMovie',
    'startTime'    => '2010-03-01T12:12:12Z',
    'endTime'      => '2010-03-04T12:12:12Z',
    'layers'       => '[3,1,100],[4,1,100]',
    'events'       => '[AR,HMI_HARP;SPoCA,1],[CH,all,1]',
    'eventsLabels' => 'true',
    'imageScale'   => '21.04',
    'x1'           => '-1000',
    'y1'           => '-3000',
    'x2'           => '5000',
    'y2'           => '5000'
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