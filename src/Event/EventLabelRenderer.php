<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * Draws event labels onto a frame: white text over a soft black outline
 * (dimmed for far-side events), one line per "\n", 12 px apart.
 *
 * A movie draws the same labels on every frame, only at different positions,
 * and drawing text with annotateImage was ~20% of a busy frame. So each label
 * is rendered once into a small transparent sprite and composited at the
 * label's position. "Over" compositing is associative, so outline-then-text on
 * a sprite placed over the frame matches drawing them straight onto it, up to
 * rounding. Sprites are kept per process (one movie job), up to MAX_SPRITES.
 *
 * A sprite can only be placed on whole pixels, so a position with a fractional
 * part (follow-viewport movies) is drawn straight onto the frame as before.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class EventLabelRenderer
{
    private const FONT_SIZE = 10;
    private const LINE_HEIGHT = 12;

    /** Margin (px) around the text in a sprite: the 3 px outline, antialiasing and glyph overhang. */
    private const PAD = 6;

    /** Upper bound on cached sprites; the cache is dropped when it is reached. */
    private const MAX_SPRITES = 500;

    /** @var array<string, array{0: \Imagick, 1: int, 2: int}> sprite, x and y of its text origin */
    private static array $sprites = [];

    /**
     * Draw $label with its first line's baseline starting at ($x, $y).
     *
     * @param bool $behind far-side event: fainter outline, 60% text
     */
    public static function draw(\Imagick $image, string $label, bool $behind, float $x, float $y): void
    {
        $lines = array_map([self::class, 'fixUnicode'], explode("\n", $label));

        if ($x !== floor($x) || $y !== floor($y)) {
            self::annotate($image, $lines, $behind, $x, $y);
            return;
        }

        // Text size depends on the image resolution (font size is in points).
        $res = $image->getImageResolution();
        $key = ($behind ? 'b' : 'n') . '|' . $res['x'] . 'x' . $res['y'] . '|' . $label;
        if (!isset(self::$sprites[$key])) {
            if (count(self::$sprites) >= self::MAX_SPRITES) {
                self::clearCache();
            }
            self::$sprites[$key] = self::sprite($lines, $behind, $res);
        }
        [$sprite, $originX, $originY] = self::$sprites[$key];
        $image->compositeImage($sprite, \Imagick::COMPOSITE_OVER, (int) $x - $originX, (int) $y - $originY);
    }

    /** Free the cached sprites. */
    public static function clearCache(): void
    {
        foreach (self::$sprites as [$sprite]) {
            $sprite->destroy();
        }
        self::$sprites = [];
    }

    /** Number of cached sprites (for tests). */
    public static function cacheSize(): int
    {
        return count(self::$sprites);
    }

    /**
     * Render the label onto a transparent image just big enough for it.
     *
     * @return array{0: \Imagick, 1: int, 2: int} the sprite and where in it the first baseline starts
     */
    private static function sprite(array $lines, bool $behind, array $res): array
    {
        $measure = new \Imagick();
        $measure->newImage(1, 1, new \ImagickPixel('transparent'));
        $measure->setImageResolution($res['x'], $res['y']);
        $draw = self::fillDraw($behind);
        $width = 0.0; $ascender = 0.0; $descender = 0.0;
        foreach ($lines as $line) {
            $m = $measure->queryFontMetrics($draw, $line === '' ? ' ' : $line);
            $width = max($width, $m['textWidth']);
            $ascender = max($ascender, $m['ascender']);
            $descender = min($descender, $m['descender']);
        }
        $measure->destroy();

        $originX = self::PAD;
        $originY = self::PAD + (int) ceil($ascender);
        $w = (int) ceil($width) + 2 * self::PAD;
        $h = $originY + (count($lines) - 1) * self::LINE_HEIGHT + (int) ceil(-$descender) + self::PAD;

        $sprite = new \Imagick();
        $sprite->newImage($w, $h, new \ImagickPixel('transparent'), 'png');
        $sprite->setImageResolution($res['x'], $res['y']);
        self::annotate($sprite, $lines, $behind, $originX, $originY);
        return [$sprite, $originX, $originY];
    }

    /** Outline then text for each line, straight onto $image. */
    private static function annotate(\Imagick $image, array $lines, bool $behind, float $x, float $y): void
    {
        $outline = self::outlineDraw($behind);
        $fill = self::fillDraw($behind);
        foreach ($lines as $count => $line) {
            $image->annotateImage($outline, $x, $y + $count * self::LINE_HEIGHT, 0, $line);
            $image->annotateImage($fill, $x, $y + $count * self::LINE_HEIGHT, 0, $line);
        }
        $outline->destroy();
        $fill->destroy();
    }

    /** Black outline, 3 px. */
    private static function outlineDraw(bool $behind): \ImagickDraw
    {
        $text = self::baseDraw();
        $text->setStrokeColor('#000C');
        $text->setStrokeAntialias(true);
        $text->setStrokeWidth(3);
        $text->setStrokeOpacity($behind ? 0.18 : 0.3);
        return $text;
    }

    /** White text over the outline. */
    private static function fillDraw(bool $behind): \ImagickDraw
    {
        $text = self::baseDraw();
        $text->setFillColor('#ffff');
        if ($behind) {
            $text->setFillOpacity(0.6);
        }
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);
        return $text;
    }

    private static function baseDraw(): \ImagickDraw
    {
        $text = new \ImagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(HV_ROOT_DIR . '/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(self::FONT_SIZE);
        return $text;
    }

    /** Labels arrive with some characters escaped. */
    private static function fixUnicode(string $line): string
    {
        return str_replace(
            ['u03b1', 'u03b2', 'u03b3', 'u00b1', 'u00b2', '&deg;'],
            ['α', 'β', 'γ', '±', '²', '°'],
            $line
        );
    }
}
