<?php

echo "\nTest Case: ".__FILE__;
echo "\nTest Date: ".date('Y-m-d H:i:s T', time());

include_once __DIR__.'/../../src/Module/JHelioviewer.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Config.php';

$config = new Config(__DIR__.'/../../settings/Config.ini');

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

echo "\nExpected Test Output:\n";
echo '{"uri":"jpip:\/\/api.helioviewer.org:8090\/AIA\/2014\/01\/02\/335\/2014_01_02__00_00_02_62__SDO_AIA_AIA_335.jp2"}';

echo "\n\nExecuting API call:\n";
echo '$jhv->execute()'."\n";

echo "\nActual Test Output:\n";
$jhv->execute();

echo "\n\nTest Execution Complete: ".date('Y-m-d H:i:s T', time())."\n\n";
?>