<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/JHelioviewer.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Database/ImgIndex.php';

$params = array(
    'action'   => 'getJPX',
    'startTime'=> '2014-01-01T00:00:00Z',
    'endTime'  => '2014-01-01T00:45:00Z',
    'sourceId' => '14',
    'jpip'     => 'true',
    'verbose'  => 'true',
    'cadence'  => '2'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$jhv = new Module_JHelioviewer($params);

echo "\n\nExecuting API call:\n";
echo '$jhv->execute()'."\n";

echo "\nActual Test Output:\n";
$jhv->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>