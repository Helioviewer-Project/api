<?php
/**
 * Helioviewer environment for running Resque
 */
# switch to api/
$dir = dirname(dirname(realpath($argv[0])));
chdir($dir);

require_once __DIR__."/../src/Config.php";
$config = new Config(__DIR__."/../settings/Config.ini");

require_once HV_ROOT_DIR.'/../src/Job/MovieBuilder.php';
?>