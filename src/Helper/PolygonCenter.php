<?php
/*
A fast algorithm for finding polygon pole of inaccessibility, the most distant internal point 
from the polygon outline (not to be confused with centroid), implemented as a JavaScript library. 
Useful for optimal placement of a text label on a polygon.

Site: https://github.com/mapbox/polylabel

 * @category Event
 * @package  Helioviewer
 * @author   Serge Zahniy <serge.zahniy@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
*/

function polygonToArray($polyString = '', $au_scalar = 1, $strokeWidth = 4){
	$strokeWidth=4;

	$originX=null; $originY=null; $polygonURL=null;
	
	$maxPixelScale = 0.60511022;  // arcseconds per pixel
	
	
	$polyString = str_replace(Array('POLYGON','(',')'), '', $polyString);
	foreach( explode(',', $polyString) as $xy ) {
	    list($x_coord,$y_coord) = explode(' ',$xy);
	    $x[] =  $x_coord * $au_scalar;
	    $y[] = -$y_coord * $au_scalar;
	}
	
	$originX = min($x);
	$originY = min($y);
	
	$polyOffsetX = $originX;
	$polyOffsetY = $originY;
	
	$width=0; $height=0;
	
	for ($i=0; $i<count($x); $i++) {
	    $xCoord = ($x[$i]-$originX) / $maxPixelScale;
	    $yCoord = ($y[$i]-$originY) / $maxPixelScale;
	    $polyArray[] = Array( $xCoord, $yCoord );
	
	    if ($xCoord > $width) {
	        $width = $xCoord;
	    }
	    if ($yCoord > $height) {
	        $height = $yCoord;
	    }
	}
	
	$polyWidth  = $width  + $strokeWidth;
	$polyHeight = $height + $strokeWidth;
	
	return array(
		'offsetX' => $polyOffsetX,
		'offsetY' => $polyOffsetY,
		'width' => $polyWidth,
		'height' => $polyHeight,
		'array' => array($polyArray)
	);
}	
	
function polylabel($polygon, $precision = 1, $debug = false) {
    // find the bounding box of the outer ring
    $minX = $minY = $maxX = $maxY =0;
    foreach($polygon[0] as $p){
	    if ($p[0] < $minX) $minX = $p[0];
        if ($p[1] < $minY) $minY = $p[1];
        if ($p[0] > $maxX) $maxX = $p[0];
        if ($p[1] > $maxY) $maxY = $p[1];
    }

    $width = $maxX - $minX;
    $height = $maxY - $minY;
    $cellSize = min($width, $height);
    $h = $cellSize / 2;

    // a priority queue of cells in order of their "potential" (max distance to polygon)
    $cellQueue = array();

    if ($cellSize == 0) return array('x' => $minX, 'y' => $minY);

    // cover polygon with initial cells
    for ($x = $minX; $x < $maxX; $x += $cellSize) {
        for ($y = $minY; $y < $maxY; $y += $cellSize) {
            $cellQueue[] = cell($x + $h, $y + $h, $h, $polygon);
        }
    }

    // take centroid as the first best guess
    $bestCell = getCentroidCell($polygon);

    // special case for rectangular polygons
    $bboxCell = cell($minX + $width / 2, $minY + $height / 2, 0, $polygon);
    if ($bboxCell['d'] > $bestCell['d']) $bestCell = $bboxCell;

    $numProbes = count($cellQueue);

    while (count($cellQueue)) {
        // pick the most promising cell from the queue
        $cell = array_pop($cellQueue);

        // update the best cell if we found a better one
        if ($cell['d'] > $bestCell['d']) {
            $bestCell = $cell;
            if ($debug) echo 'found best '.(round(1e4 * $cell['d']) / 1e4).' after '.$numProbes.' probes<br/>';
        }

        // do not drill down further if there's no chance of a better solution
        if ($cell['max'] - $bestCell['d'] <= $precision) continue;

        // split the cell into four cells
        $h = $cell['h'] / 2;
        $cellQueue[] = cell($cell['x'] - $h, $cell['y'] - $h, $h, $polygon);
        $cellQueue[] = cell($cell['x'] + $h, $cell['y'] - $h, $h, $polygon);
        $cellQueue[] = cell($cell['x'] - $h, $cell['y'] + $h, $h, $polygon);
        $cellQueue[] = cell($cell['x'] + $h, $cell['y'] + $h, $h, $polygon);
        $numProbes += 4;
    }

    if ($debug) {
        echo 'num probes: '.$numProbes.'<br/>';
        echo 'best distance: '.$bestCell['d'].'<br/>';
    }

    return array(
	    'x' => $bestCell['x'],
	    'y' => $bestCell['y']
    );
}

function cell($x, $y, $h, $polygon) {
	$d = pointToPolygonDist($x, $y, $polygon);
	
	return array(
		'x' => $x, // cell center x
	    'y' => $y, // cell center y
	    'h' => $h, // half the cell size
	    'd' => $d, // distance from cell center to polygon
	    'max' => $d + $h * sqrt(2) // max distance to polygon within a cell
	); 
}

// signed distance from point to polygon outline (negative if point is outside)
function pointToPolygonDist($x, $y, $polygon) {
    $inside = false;
    $minDistSq = INF;

    for ($k = 0; $k < count($polygon); $k++) {
        $ring = $polygon[$k];

        for ($i = 0, $len = count($ring), $j = $len - 1; $i < $len; $j = $i++) {
            $a = $ring[$i];
            $b = $ring[$j];

            if (($a[1] > $y != $b[1] > $y) &&
                ($x < ($b[0] - $a[0]) * ($y - $a[1]) / ($b[1] - $a[1]) + $a[0])) $inside = !$inside;

            $minDistSq = min($minDistSq, getSegDistSq($x, $y, $a, $b));
        }
    }

    return ($inside ? 1 : -1) * sqrt($minDistSq);
}

// get polygon centroid
function getCentroidCell($polygon) {
    $area = 0;
    $x = 0;
    $y = 0;
    $points = $polygon[0];

    for ($i = 0, $len = count($points), $j = $len - 1; $i < $len; $j = $i++) {
        $a = $points[$i];
        $b = $points[$j];
        $f = $a[0] * $b[1] - $b[0] * $a[1];
        $x += ($a[0] + $b[0]) * $f;
        $y += ($a[1] + $b[1]) * $f;
        $area += $f * 3;
    }
    if ($area == 0) return cell($points[0][0], $points[0][1], 0, $polygon);
    return cell($x / $area, $y / $area, 0, $polygon);
}

// get squared distance from a point to a segment
function getSegDistSq($px, $py, $a, $b) {

    $x = $a[0];
    $y = $a[1];
    $dx = $b[0] - $x;
    $dy = $b[1] - $y;

    if ($dx != 0 || $dy != 0) {

        $t = (($px - $x) * $dx + ($py - $y) * $dy) / ($dx * $dx + $dy * $dy);

        if ($t > 1) {
            $x = $b[0];
            $y = $b[1];

        } else if ($t > 0) {
            $x += $dx * $t;
            $y += $dy * $t;
        }
    }

    $dx = $px - $x;
    $dy = $py - $y;

    return $dx * $dx + $dy * $dy;
}