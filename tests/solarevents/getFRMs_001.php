<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/SolarEvents.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';


$params = array(
    'action'    => 'getFRMs',
    'startTime' => '2014-02-28T00:00:00.000Z',
    'endTime'   => '2014-03-02T23:59:59.999Z'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$solarEvents = new Module_SolarEvents($params);

echo "\n\nExecuting API call:\n";
echo '$solarEvents->execute()'."\n";


echo "\nActual Test Output:\n";
$solarEvents->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>