<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/WebClient.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';
include_once __DIR__.'/../../src/Helper/ErrorHandler.php';


$params = array(
    'action'     => 'getTile',
    'id'         => '36275490',
    'x'          => '-1',
    'y'          => '-1',
    'imageScale' => '2.42044088'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$webClient = new Module_WebClient($params);

echo "\n\nExecuting API call:\n";
echo '$webClient->execute()'."\n";

echo "\n\nDump PNG binary data to stdout?";
echo "\n'y' or 'n': ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if ( strtolower(trim($line)) != 'y' ) {

    echo "\n\nTest Script ABORTED: ".date('Y-m-d H:i:s T', time())."\n\n";
    exit;
}

echo "\nActual Test Output:\n";
$webClient->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>