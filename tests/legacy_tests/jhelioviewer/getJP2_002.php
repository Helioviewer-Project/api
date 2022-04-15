<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/JHelioviewer.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';

$params = array(
    'action'   => 'getJP2Image',
    'date'     => '2014-01-01T23:59:59Z',
    'sourceId' => '14',
    'jpip'     => 'true',
    'json'     => 'true'
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