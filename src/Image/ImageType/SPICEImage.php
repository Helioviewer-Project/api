<?php
/**
 * Image_ImageType_SPICEImage class definition
 *
 * @category Image
 * @package  Helioviewer
 * @author   Salim Hachemaoui
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */

require_once HV_ROOT_DIR.'/../src/Image/HelioviewerImage.php';

/**
 * Handles Solar Orbiter SPICE images.
 *
 * SPICE images are rendered in grayscale without applying a color table.
 */
class Image_ImageType_SPICEImage extends Image_HelioviewerImage
{
}
?>
