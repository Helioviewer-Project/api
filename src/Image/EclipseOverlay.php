<?php declare(strict_types=1);

/**
 * Renders eclipse information over the given composite image
 * The entry point to this static class is "Apply"
 * When applying the overlay, the following steps are taken:
 * - Draw the countdown to the eclipse at the top of the image, if applicable
 * - Add a 'UTC' time designation to the LASCO C2/C3 timestamps
 * - Add a delta time (time image was taken relative to the eclipse.) descriptor above the LASCO C2/C3 labels.
 * - Add a moon for scale.
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
    static function Apply(IMagick $image, int $year, DateTimeImmutable $imageDate, bool $enableCountdown = true) {
        $ECLIPSE_TIMES = [
            // https://science.nasa.gov/eclipses/future-eclipses/eclipse-2024/where-when/
            2024 => new DateTimeImmutable('2024-04-08 19:07:00', new DateTimeZone('UTC'))
        ];

        include_once __DIR__ . "/EditableImage.php";
        $editableImage = new Image_EditableImage($image);
        // Draw the countdown if it's requested
        if ($enableCountdown) {
            Image_EclipseOverlay::ApplyCountdown($editableImage, $ECLIPSE_TIMES[$year]);
        }

        // Add 'UTC' to the end of the LASCO dates
        Image_EclipseOverlay::ApplyUTCLabels($editableImage);

        // Add the time the image was taken relative to the eclipse
        Image_EclipseOverlay::ApplyDeltaLabel($editableImage, $imageDate, $ECLIPSE_TIMES[$year]);
    }

    static private function ApplyDeltaLabel(Image_EditableImage $editableImage, DateTimeImmutable $imageDate, DateTimeImmutable $eclipseDate) {
        $delta = $eclipseDate->diff($imageDate);
        $days = $delta->days;
        $dayLabel = Image_EclipseOverlay::_pluralize("day", $days);
        $hours = $delta->h;
        $hourLabel = Image_EclipseOverlay::_pluralize("hour", $hours);
        $minutes = $delta->m;
        $minuteLabel = Image_EclipseOverlay::_pluralize("minute", $minutes);
        $seconds = $delta->s;
        $secondLabel = Image_EclipseOverlay::_pluralize("second", $seconds);
        $deltaLabel = "Images taken $days $dayLabel $hours $hourLabel $minutes $minuteLabel $seconds $secondLabel before eclipse";
        $editableImage->Write(12, 12, 608-15, $deltaLabel);
    }

    static private function ApplyUTCLabels(Image_EditableImage $img) {
        $img->Write(12, 270, 608, "UTC");
        $img->Write(12, 270, 623, "UTC");
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