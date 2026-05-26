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

// Redirect PHP's error_log destination off stderr. PHPUnit's
// @runInSeparateProcess tests use stderr as their IPC channel with the child
// process, so any stray error_log() call in production code corrupts the IPC
// and surfaces as a PHPUnit\Framework\Exception. Sending to a temp file keeps
// the messages around for inspection without breaking the tests.
ini_set('log_errors', '1');
ini_set('error_log', sys_get_temp_dir() . '/helioviewer-test.log');

// Load Helioviewer Configuration. This defines all the HV_* variables
// seen throughout the project
require_once __DIR__ . '/../src/Config.php';
$config = new Config(__DIR__ . '/../settings/Config.ini');

// Disable Sentry during testing
Helioviewer\Api\Sentry\Sentry::init(['enabled' => false]);
