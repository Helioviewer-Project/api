<?php declare(strict_types=1);

namespace Helioviewer\Api\Movie;

/**
 * Draws the layer names of the timestamp watermark: white text over a 2 px
 * black outline, one line per layer.
 *
 * Drawing outlined text with annotateImage is slow (each stroked glyph is
 * rendered as a vector path, ~35 ms for one layer's name, ~90 ms for three),
 * and a movie draws the same names on every frame. So the names are rendered
 * once into a small transparent sprite and composited at their position.
 * "Over" compositing is associative, so outline-then-text on a sprite placed
 * over the frame matches drawing them straight onto it, up to rounding.
 * Sprites are kept per process (one movie job), up to MAX_SPRITES.
 *
 * A sprite can only be placed on whole pixels, so a position with a
 * fractional part is drawn straight onto the image.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class WatermarkNames
{
    private const FONT_SIZE = 12;

    /** Margin (px) around the text in a sprite: the outline, antialiasing and glyph overhang. */
    private const PAD = 6;

    /** Upper bound on cached sprites; the cache is dropped when it is reached. */
    private const MAX_SPRITES = 50;

    /** @var array<string, array{0: \Imagick, 1: int, 2: int}> sprite, x and y of its text origin */
    private static array $sprites = [];

    /**
     * Draw $names (lines separated by "\n") with the first line's baseline starting at ($x, $y).
     */
    public static function draw(\Imagick $image, string $names, float $x, float $y): void
    {
        if ($x !== floor($x) || $y !== floor($y)) {
            self::annotate($image, $names, $x, $y);
            return;
        }

        // Text size depends on the image resolution (font size is in points).
        $res = $image->getImageResolution();
        $key = $res['x'] . 'x' . $res['y'] . '|' . $names;
        if (!isset(self::$sprites[$key])) {
            if (count(self::$sprites) >= self::MAX_SPRITES) {
                self::clearCache();
            }
            self::$sprites[$key] = self::sprite($names, $res);
        }
        [$sprite, $originX, $originY] = self::$sprites[$key];
        $image->compositeImage($sprite, \Imagick::COMPOSITE_OVER, (int) $x - $originX, (int) $y - $originY);
    }

    /** The outline draw, as used for the names; the dates are drawn with the same style. */
    public static function outlineDraw(): \ImagickDraw
    {
        $text = self::baseDraw();
        $text->setStrokeColor(new \ImagickPixel('#000C'));
        $text->setStrokeAntialias(true);
        $text->setStrokeWidth(2);
        return $text;
    }

    /** The white text draw, as used for the names; the dates are drawn with the same style. */
    public static function textDraw(): \ImagickDraw
    {
        $text = self::baseDraw();
        $text->setFillColor(new \ImagickPixel('white'));
        $text->setTextAntialias(true);
        $text->setStrokeWidth(0);
        return $text;
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
     * Render the names onto a transparent image big enough for them.
     *
     * @return array{0: \Imagick, 1: int, 2: int} the sprite and where in it the first baseline starts
     */
    private static function sprite(string $names, array $res): array
    {
        $measure = new \Imagick();
        $measure->newImage(1, 1, new \ImagickPixel('transparent'));
        $measure->setImageResolution($res['x'], $res['y']);
        $draw = self::outlineDraw();
        $m = $measure->queryFontMetrics($draw, $names === '' ? ' ' : $names, true);
        $draw->destroy();
        $measure->destroy();

        // textHeight covers every line, so ascender + textHeight leaves room below the last baseline.
        $originX = self::PAD;
        $originY = self::PAD + (int) ceil($m['ascender']);
        $w = (int) ceil($m['textWidth']) + 2 * self::PAD;
        $h = $originY + (int) ceil($m['textHeight']) + self::PAD;

        $sprite = new \Imagick();
        $sprite->newImage($w, $h, new \ImagickPixel('transparent'), 'png');
        $sprite->setImageResolution($res['x'], $res['y']);
        self::annotate($sprite, $names, $originX, $originY);
        return [$sprite, $originX, $originY];
    }

    /** Outline then text, straight onto $image. */
    private static function annotate(\Imagick $image, string $names, float $x, float $y): void
    {
        $outline = self::outlineDraw();
        $text = self::textDraw();
        $image->annotateImage($outline, $x, $y, 0, $names);
        $image->annotateImage($text, $x, $y, 0, $names);
        $outline->destroy();
        $text->destroy();
    }

    private static function baseDraw(): \ImagickDraw
    {
        $text = new \ImagickDraw();
        $text->setTextEncoding('utf-8');
        $text->setFont(HV_ROOT_DIR . '/../resources/fonts/DejaVuSans.ttf');
        $text->setFontSize(self::FONT_SIZE);
        return $text;
    }
}
