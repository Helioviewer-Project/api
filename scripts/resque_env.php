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

// A movie holds the events of all its frames in memory: a 300-frame movie
// with events needs about 130 MB, over PHP's default limit of 128 MB.
ini_set('memory_limit', '512M');

use Helioviewer\Api\Sentry\Sentry;

Sentry::init([
    'environment' => HV_APP_ENV ?? 'dev',
    'sample_rate' => HV_SENTRY_SAMPLE_RATE ?? 0.1,
    'enabled' => HV_SENTRY_ENABLED ?? false,
    'dsn' => HV_SENTRY_DSN,
]);

Sentry::setTag('Type', 'queue');

require_once HV_ROOT_DIR.'/../src/Job/MovieBuilder.php';
require_once HV_ROOT_DIR.'/../src/Job/MoviePiece.php';
?>
