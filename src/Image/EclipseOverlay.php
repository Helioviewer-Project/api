<?php declare(strict_types=1);

/**
 * Renders eclipse information over the given composite image
 * 
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
class Image_EclipseOverlay {
    const NUMBER_SIZE = 24;
    const LABEL_SIZE = 16;
    const SPACE_SIZE = 4;
    // Applies the eclipse overlay information to the given image.
    static function Apply(IMagick $image, int $year, bool $enableCountdown = true) {
        $ECLIPSE_TIMES = [
            // https://science.nasa.gov/eclipses/future-eclipses/eclipse-2024/where-when/
            2024 => new DateTimeImmutable('2024-04-08 19:07:00', new DateTimeZone('UTC'))
        ];

        include_once __DIR__ . "/EditableImage.php";
        $editableImage = new Image_EditableImage($image);
        if ($enableCountdown) {
            Image_EclipseOverlay::ApplyCountdown($editableImage, $ECLIPSE_TIMES[$year]);
        }
    }

    /**
     * Adds an s to the end of the text if the given value is not 1.
     */
    static private function _pluralize(string $text, int $value): string {
        if ($value != 1) {
            return $text . 's';
        } else {
            return $text;
        }
    }

    /**
     * Computes the width of the given text label for the countdown
     * i.e. label = day, value = 2 will return the width of "2 days"
     * @param Image_EditableImage $img Image being drawn on
     * @param string $label Label to compute (year, month, day, hour, etc)
     * @param int $value Number of units (required for pluralization)
     * @param bool $space Include a space at the end
     */
    static private function _ComputeWidth(Image_EditableImage $img, string $label, int $value, bool $space): float {
        $text = Image_EclipseOverlay::_pluralize($label, $value);
        $textWidth = $img->GetTextWidth(Image_EclipseOverlay::LABEL_SIZE, $text);
        $numberWidth = $img->GetTextWidth(Image_EclipseOverlay::NUMBER_SIZE, "$value ");
        $width = $textWidth + $numberWidth;
        if ($space) {
            $width += $img->GetTextWidth(Image_EclipseOverlay::NUMBER_SIZE, " ");
        }
        return $width;
    }

    static private function _ComputeCountdownWidth(Image_EditableImage $img, DateInterval $interval): float {
        // Get text width for days
        $width = Image_EclipseOverlay::_ComputeWidth($img, "day", $interval->d, true);
        $width += Image_EclipseOverlay::_ComputeWidth($img, "hour", $interval->h, true);
        $width += Image_EclipseOverlay::_ComputeWidth($img, "minute", $interval->m, true);
        $width += Image_EclipseOverlay::_ComputeWidth($img, "second", $interval->s, false);
        return $width;
    }

    static private function _WriteCountdownLabel(Image_EditableImage $img, string $label, int $value, bool $space) {
        $text = Image_EclipseOverlay::_pluralize($label, $value);
        $img->WriteCursor(Image_EclipseOverlay::NUMBER_SIZE, "$value ");
        $img->WriteCursor(Image_EclipseOverlay::LABEL_SIZE, $text);
        if ($space) {
            $img->WriteCursor(Image_EclipseOverlay::NUMBER_SIZE, " ");
        }
    }

    static private function ApplyCountdown(Image_EditableImage $img, DateTimeImmutable $eclipse_date) {
        $now = new DateTimeImmutable("now", new DateTimeZone("UTC"));
        $dt = $eclipse_date->diff($now);
        $width = Image_EclipseOverlay::_ComputeCountdownWidth($img, $dt);
        $x = ($img->width / 2) - ($width / 2);
        $img->WriteCentered(Image_EclipseOverlay::NUMBER_SIZE - 4, $img->width/2, 50, "Eclipse Starts In");
        $img->SetCursor($x, 78);
        Image_EclipseOverlay::_WriteCountdownLabel($img, "day", $dt->days, true);
        Image_EclipseOverlay::_WriteCountdownLabel($img, "hour", $dt->h, true);
        Image_EclipseOverlay::_WriteCountdownLabel($img, "minute", $dt->m, true);
        Image_EclipseOverlay::_WriteCountdownLabel($img, "second", $dt->s, true);
    }
}