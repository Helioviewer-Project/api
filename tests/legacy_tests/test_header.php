<?php

echo "\nTest Case: ".$_SERVER['SCRIPT_FILENAME'];
echo "\nTest Date: ".date('Y-m-d H:i:s T', time());

include_once __DIR__.'/../src/Config.php';
$config = new Config(__DIR__.'/../settings/Config.ini');

?>