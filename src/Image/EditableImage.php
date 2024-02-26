<?php declare(strict_types=1);

/**
 * Wrapper for IMagickDraw to be more object oriented
 */
class IMagickProperties {
    public IMagickDraw $drawer;
    private IMagickPixel $color;

    public function __construct(int $size, string $color, bool $outline)
    {
        $this->color = new IMagickPixel($color);

        $this->drawer = new IMagickDraw();
        $this->drawer->setTextEncoding('utf-8');
        $this->drawer->setFont(HV_ROOT_DIR.'/../resources/fonts/DejaVuSans.ttf');
        $this->drawer->setFontSize($size);
        if ($outline) {
            $this->drawer->setStrokeColor($this->color);
            $this->drawer->setStrokeAntialias(true);
            $this->drawer->setStrokeWidth(2);
        } else {
            $this->drawer->setFillColor($this->color);
            $this->drawer->setTextAntialias(true);
            $this->drawer->setStrokeWidth(0);
        }

    }

    public function __destruct()
    {
        $this->color->destroy();
        $this->drawer->destroy();
    }
}

/**
 * Wrapper for image magick to make it easier to programmatically edit images
 * 
 * @category Image
 * @package  Helioviewer
 * @author   Daniel Garcia Briseno <daniel.garciabriseno@nasa.gov>
 * @license  http://www.mozilla.org/MPL/MPL-1.1.html Mozilla Public License 1.1
 * @link     https://github.com/Helioviewer-Project
 */
class Image_EditableImage {
    private IMagick $image;
    public int $width;
    public int $height;

    // Cursor positions are used when writing onto the image via the cursor
    private $cursor_x;
    private $cursor_y;

    public function __construct(IMagick $image)
    {
        $this->width = $image->getImageWidth();
        $this->height = $image->getImageHeight();
        $this->image = $image;
        $this->cursor_x = 0;
        $this->cursor_y = 0;
    }

    public function GetTextWidth(int $size, string $text): float {
        $props = new IMagickProperties($size, '#000C', true);
        $metrics = $this->image->queryFontMetrics($props->drawer, $text);
        return $metrics['textWidth'];
    }

    /**
     * Writes text with its center at the given location.
     */
    public function WriteCentered(int $size, float $x, float $y, string $text) {
        $width = $this->GetTextWidth($size, $text);
        $offset = $x - ($width / 2);
        $this->Write($size, $offset, $y, $text);
    }

    /**
     * Writes text on the image at the given location
     * @param int $size    Font size
     * @param int $x       Horizontal offset
     * @param int $y       Vertical offset from top of image
     * @param string $text Text to write on the image
     */
    public function Write(int $size, float $x, float $y, string $text) {
        $outline = new IMagickProperties($size, '#000C', true);
        $this->image->annotateImage($outline->drawer, $x, $y, 0, $text);

        $foreground = new IMagickProperties($size, 'white', false);
        $this->image->annotateImage($foreground->drawer, $x, $y, 0, $text);
    }

    /**
     * Sets the cursor position
     */
    public function SetCursor(float $x, float $y) {
        $this->cursor_x = $x;
        $this->cursor_y = $y;
    }

    /**
     * Writes the given text at the cursor position and updates the cursor
     * position.
     */
    public function WriteCursor(int $size, string $text) {
        $this->Write($size, $this->cursor_x, $this->cursor_y, $text);
        $width = $this->GetTextWidth($size, $text);
        $this->cursor_x += $width;
    }

    /**
     * Overlay an image file onto this image at the given position and scale
     * @param string $file Image file to be overlayed
     * @param float $x Position on this image where the new image will be placed
     * @param float $y Position on this image where the new image will be placed
     * @param float $width Width of the overlay image
     * @param float $height Height of the overlayimage
     * @param int $filter scaling filter if width/height does not match the overlayed image, see imagick.constant.filters
     */
    public function Overlay(string $file, float $x, float $y, float $width, float $height, int $filter = imagick::FILTER_CUBIC) {
        $overlay = new IMagick($file);
        $overlay->resizeImage((int)$width, (int)$height, $filter, 1);
        $overlay->modulateImage(30, 100, 100);
        $offsetX = $x - ($width / 2);
        $offsetY = $y - ($height / 2);
        $this->image->compositeImage($overlay, imagick::COMPOSITE_COPY, (int) $offsetX, (int)$offsetY);
        $overlay->destroy();
    }
}