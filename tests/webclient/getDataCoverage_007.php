<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/WebClient.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';


$params = array(
    'action'     => 'getDataCoverage',
    'resolution' => '3M',
    'endDate'    => '2014-03-31T23:59:59.999Z'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$webClient = new Module_WebClient($params);

echo "\n\nExecuting API call:\n";
echo '$webClient->execute()'."\n";


echo "\nActual Test Output:\n";
$webClient->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>