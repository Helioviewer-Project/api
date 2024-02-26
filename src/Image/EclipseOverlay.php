<?php declare(strict_types=1);

/**
 * Renders eclipse information over the given composite image
 * The entry point to this static class is "Apply"
 * When applying the overlay, the following steps are taken:
 * - Add a 'UTC' time designation to the LASCO C2/C3 timestamps
 * - Optionally add a moon for scale.
 * 
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
class Image_EclipseOverlay {
    // Applies the eclipse overlay information to the given image.
    static function Apply(IMagick $image, float $scale, bool $showMoon) {

        include_once __DIR__ . "/EditableImage.php";
        $editableImage = new Image_EditableImage($image);

        // Add 'UTC' to the end of the dates
        Image_EclipseOverlay::ApplyUTCLabels($editableImage);

        if ($showMoon) {
            // Add the moon image on top
            Image_EclipseOverlay::ApplyMoon($editableImage, $scale);
        }
    }

    /**
     * @param float $scale Image scale, arcseconds / pixel
     */
    static private function ApplyMoon(Image_EditableImage $img, float $scale) {
        // Assume that the center of the image is where the sun ought to be.
        $center_x = $img->width / 2 + 1;
        $center_y = $img->height / 2 + 1;
        // 1920 is approximately the diameter of the sun in arcseconds.
        // 1920 arcseconds / (scale arcseconds/pixel) = image size in pixels.
        $moon_width = 1920 / $scale;
        $moon_height = 1920 / $scale;
        $img->Overlay(HV_ROOT_DIR . "/../resources/images/moon.png", $center_x, $center_y, $moon_width, $moon_height);
    }

    static private function ApplyUTCLabels(Image_EditableImage $img) {
        $img->Write(12, 270, $img->height - 25, "UTC");
    }
}