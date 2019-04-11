<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 softtabstop=4: */
/**
 * Parallel Movie Frame Launcher
 * Provides a way to launch the movie frame creation in parallel
 *
 * @category Helper
 * @package  Helioviewer
 * @author   Kirill Vorobyev <kirill.g.vorobyev@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
define('HV_ROOT', getenv("HV_ROOT_DIR"));

require_once HV_ROOT . '/../src/Config.php';
$config = new Config(HV_ROOT .'/../settings/Config.ini');

require_once HV_ROOT_DIR .'/../src/Image/Composite/HelioviewerMovieFrame.php';
require_once HV_ROOT_DIR . '/../src/Helper/HelioviewerEvents.php';
require_once HV_ROOT_DIR . '/../src/Helper/HelioviewerLayers.php';
require_once HV_ROOT_DIR . '/../src/Helper/RegionOfInterest.php';

$params = json_decode($argv[1]);

$filepath = $params->{"filepath"};
$eventsLabels = $params->{"eventsLabels"};
$movieIcons = $params->{"movieIcons"};
$celestialBodies = (Array)$params->{"celestialBodies"};
$scale = $params->{"scale"};
$scaleType = $params->{"scaleType"};
$scaleX = $params->{"scaleX"};
$scaleY = $params->{"scaleY"};
$obsDate = $params->{"obsDate"};
$options = (Array)$params->{"options"};
// Data Layers
$layers = new Helper_HelioviewerLayers($params->{'dataSourceString'});
$events = new Helper_HelioviewerEvents($params->{'eventSourceString'});

// Regon of interest
$roi = Helper_RegionOfInterest::parsePolygonString($params->{'roiSourceString'}, $params->{'imageScaleSourceString'});

$screenshot = new Image_Composite_HelioviewerMovieFrame(
    $filepath, $layers, $events,
    $eventsLabels, $movieIcons, $celestialBodies, 
    $scale, $scaleType, $scaleX, $scaleY,
    $obsDate, $roi, $options);

printf(".");
        
?>