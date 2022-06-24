<?php 
/**
 * Generates color tables for use with Helioviewer given
 * a file containing RGB values.
 * Created for Solar Orbiter's HRI-LYA color table.
 * See https://github.com/Helioviewer-Project/api/issues/149#issuecomment-1164705295
 * @author Daniel Garcia-Briseno <daniel.garciabriseno@nasa.gov>
 */

/**
 * Parses the given text file into an array of RGB values
 * The color file should be in the form:
 * "red1 green1 blue1
 *  red2 green2 blue2
 *  ..."
 * The result is an array parsed into rgb values:
 * array(
 *   [0] => array('r' => red1, 'g' => green1, 'b' => blue1),
 *   [1] => array('r' => red2, 'g' => green2, 'b' => blue2),
 *   ...);
 */
function readColorFile($color_file) {
    echo "Using color table description $color_file\n";
    // Make sure the file exists, otherwise quit.
    if (!file_exists($color_file)) {
        echo "$color_file does not exist. Exiting.\n";
	exit(1);
    }

    $color_data = file_get_contents($color_file);
    $lines = explode("\n", $color_data);
    $result = array();
    foreach($lines as $line) {
        $colors = preg_split("/\s+/", $line);
        array_push($result, array(
            'r' => intval($colors[0]),
            'g' => intval($colors[1]),
            'b' => intval($colors[2])
        ));
    }
    return $result;
}

/**
 * Uses image magick to construct an image using the array of
 * RGB values.
 * $colors is an array of colors in the form
 * array(
 *   [0] => array('r' => red1, 'g' => green1, 'b' => blue1),
 *   [1] => array('r' => red2, 'g' => green2, 'b' => blue2),
 *   ...);
 */
function constructColorTable($colors) {
    // Construct the image instance
    $colorTable = new \Imagick();
    $colorTable->newImage(1, count($colors), "SteelBlue2");
    $colorTable->setImageFormat("png");

    // Construct the drawer
    $draw = new \ImagickDraw();
    // Define the points to draw
    $index = 0;
    foreach($colors as $color) {
        $fillColor = new \ImagickPixel();
        $fillDescription = 'rgba('.$color['r'].', '.$color['g'].', '.$color['b'].', 1.0)';
        $fillColor->setColor($fillDescription);
        $draw->setFillColor($fillColor);
        $draw->point(0, $index);
        $index += 1;
    }
    $colorTable->drawImage($draw);
    $colorTable->writeImage('color_table.png');
    echo "Created color_table.png\n";
}

$colors = readColorFile($argv[1]);
constructColorTable($colors);
?>
