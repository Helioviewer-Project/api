<?php
/**
 * Helioviewer environment for running Resque
 */
# switch to api/
$dir = dirname(dirname(realpath($argv[0])));
chdir($dir);

require_once __DIR__.'/../vendor/autoload.php'; 
require_once __DIR__."/../src/Config.php";

$config = new Config(__DIR__."/../settings/Config.ini");

$sentry = Sentry::get([
    'environment' => HV_APP_ENV ?? 'dev',
    'sample_rate' => HV_SENTRY_SAMPLE_RATE ?? 0.1,
    'enabled' => HV_SENTRY_ENABLED ?? false,
    'dsn' => HV_SENTRY_DSN,
]);

require_once HV_ROOT_DIR.'/../src/Job/MovieBuilder.php';
?>
