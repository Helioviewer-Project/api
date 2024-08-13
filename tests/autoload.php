<?php
/**
 * @package  Helioviewer\Tests
 * @author Daniel Garcia-Briseno
 *
 * This header is meant to be included at the start of each test. It
 * gets the config and settings from the main source so that it
 * doesn't need to be included in every test file (in the way it is
 * included in every main php source file...).
 */

// Load Helioviewer Configuration. This defines all the HV_* variables
// seen throughout the project
require_once __DIR__ . '/../src/Config.php';
$config = new Config(__DIR__ . '/../settings/Config.ini');

// Disable Sentry during testing
define('HV_'.strtoupper('sentry_enabled'), false);


