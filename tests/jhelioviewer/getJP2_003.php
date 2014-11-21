<?php
include_once __DIR__.'/../../tests/test_header.php';

include_once __DIR__.'/../../src/Module/JHelioviewer.php';
include_once __DIR__.'/../../src/Validation/InputValidator.php';

$params = array(
    'action'   => 'getJP2Image',
    'date'     => '2014-01-01T23:59:59Z',
    'sourceId' => '14'
);

echo "\n\nInput to test case:\n";
echo '$params => ';
var_dump($params);

echo "\nInitializing Object";
$jhv = new Module_JHelioviewer($params);

echo "\n\nOutputting evidence that input was accepted:\n";
echo '$jhv => ';
var_dump($jhv);

echo "\n\nDump JP2 binary data to stdout?";
echo "\n'y' or 'n': ";
$handle = fopen ("php://stdin","r");
$line = fgets($handle);
if ( strtolower(trim($line)) != 'y' ) {
    $params['jpip'] = 'true';
    $jhv = new Module_JHelioviewer($params);
    $jhv->execute();

    echo "\nTest Script ABORTED: ".date('Y-m-d H:i:s T', time())."\n\n";
    exit;
}
echo "\n\nExecuting API call:\n";
echo '$jhv->execute()'."\n";

echo "\nActual Test Output:\n";
$jhv->execute();

include_once __DIR__.'/../../tests/test_footer.php';
?>