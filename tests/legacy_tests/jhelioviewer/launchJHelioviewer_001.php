<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/JHelioviewer.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';

$params = array(
    'action'     => 'launchJHelioviewer',
    'startTime'  => '2014-02-28T13:12:34.567Z',
    'endTime'    => '2014-03-31T23:54:32.109Z',
    'imageScale' => '4.8408818',
    'layers'     => '[SDO,AIA,171],[SDO,AIA,193]'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$jhv = new Module_JHelioviewer($params);

echo "\n\nOutputting evidence that input was accepted:\n";
echo '$jhv => ';
var_dump($jhv);

echo "\n\nExecuting API call:\n";
echo '$jhv->execute()'."\n";

echo "\nActual Test Output:\n";
$jhv->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>