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
 * rounding. Sprites are kept per process, up to MAX_SPRITES.
 *
 * When a movie is built as pieces, every piece is a process of its own and
 * would render every label again. So the sprites can also be shared through
 * a folder, $spriteDir, next to the movie's frames: a sprite is read from
 * there when it exists and written there once rendered, as a small 16-bit
 * MIFF so the pixels stay exact. The pieces of a movie start together with
 * the same labels to draw, so the first one to claim a label (a lock file
 * next to the sprite) renders it and the others wait for its file instead of
 * rendering the same label at the same time.
 *
 * The outline is not a stroke. ImageMagick 6 draws a stroked glyph through
 * its general path rasteriser, which walks the whole canvas for every glyph:
 * 30 ms per line of text on a 64-core box, against 0.8 ms for a filled one,
 * and the 3500 labels of a month-long movie made that the longest part of
 * the overlays. A sprite draws the glyphs once as a mask, grows it by one
 * pixel and by two (a dilation: the maximum over a disc, a neighbourhood
 * filter over the small sprite), fades each to the darkness the stroke left
 * at that distance (HALO), lays the two rings and the black glyphs over each
 * other and the white text on top: 5 ms for a two-line label where the
 * stroke took 58, and the same look to within antialiasing.
 *
 * A sprite can only be placed on whole pixels, so a position with a fractional
 * part (follow-viewport movies) is drawn straight onto the frame as before,
 * with the stroked outline.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class EventLabelRenderer
{
    private const FONT_SIZE = 10;
    private const LINE_HEIGHT = 12;

    /** Margin (px) around the text in a sprite: the 3 px outline, antialiasing and glyph overhang. */
    private const PAD = 6;

    /**
     * The halo of a sprite, two rings of black over the glyphs: the pixels one
     * out of a glyph at HALO[0], those two out at HALO[1], for a near-side and
     * a far-side event. Measured off the 3 px stroke it replaces: a stroke
     * draws its segments one over another, so the pixel next to a glyph came
     * out 61% black (54% for a far-side event) and the one after it 16% (10%),
     * nothing like the stroke's nominal OUTLINE_OPACITY.
     */
    private const HALO = [0.60, 0.22];
    private const HALO_BEHIND = [0.54, 0.13];

    /** Disc radii of the dilations that reach one and two pixels out of a glyph. */
    private const HALO_RADII = [1.5, 2.0];

    /** Opacity of the stroked outline (fractional positions), for a near-side and a far-side event. */
    private const OUTLINE_OPACITY = 0.3;
    private const OUTLINE_OPACITY_BEHIND = 0.18;

    /** Upper bound on cached sprites; the cache is dropped when it is reached. */
    private const MAX_SPRITES = 500;

    /** Seconds a job waits for a sprite another job is rendering before it renders the label itself. */
    private const LOCK_WAIT = 3.0;

    /** Microseconds between two looks for that sprite. */
    private const LOCK_POLL_US = 10000;

    /** A lock older than this (seconds) belongs to a job that died: it is ignored. */
    private const LOCK_STALE = 30;

    /** @var array<string, array{0: \Imagick, 1: int, 2: int}> sprite, x and y of its text origin */
    private static array $sprites = [];

    /** Folder the sprites are shared through between processes (with a trailing slash), or null: in memory only. */
    public static ?string $spriteDir = null;

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
        [$sprite, $originX, $originY] = self::cached($label, $lines, $behind, $image->getImageResolution());
        $image->compositeImage($sprite, \Imagick::COMPOSITE_OVER, (int) $x - $originX, (int) $y - $originY);
    }

    /** The sprite from memory, else from $spriteDir, else rendered (and kept in both). */
    private static function cached(string $label, array $lines, bool $behind, array $res): array
    {
        $key = self::key($label, $behind, $res);
        if (!isset(self::$sprites[$key])) {
            if (count(self::$sprites) >= self::MAX_SPRITES) {
                self::clearCache();
            }
            self::$sprites[$key] = self::load($key) ?? self::render($key, $lines, $behind, $res);
        }
        return self::$sprites[$key];
    }

    /**
     * Render the sprite and, when the sprites are shared, store it. The
     * overlay jobs of a movie start at the same moment with the same labels
     * to draw, so the first to claim a label (an exclusive lock file) renders
     * it and the others wait for its sprite, up to LOCK_WAIT, instead of all
     * rendering it; one that waited in vain renders it itself.
     */
    private static function render(string $key, array $lines, bool $behind, array $res): array
    {
        if (self::$spriteDir === null) {
            return self::sprite($lines, $behind, $res);
        }
        @mkdir(self::$spriteDir, 0775, true);
        $lock = self::path($key) . '.lock';
        $mine = @fopen($lock, 'x');                       // atomic: exactly one job gets it
        if ($mine === false) {
            $deadline = microtime(true) + self::LOCK_WAIT;
            do {
                usleep(self::LOCK_POLL_US);
                clearstatcache(true, self::path($key));
                $entry = self::load($key);
                if ($entry !== null) {
                    return $entry;
                }
                clearstatcache(true, $lock);
                $held = is_file($lock) && filemtime($lock) >= time() - self::LOCK_STALE;
            } while ($held && microtime(true) < $deadline);
            $entry = self::sprite($lines, $behind, $res); // the holder died or is too slow
            self::store($key, $entry);
            return $entry;
        }
        fclose($mine);
        try {
            $entry = self::sprite($lines, $behind, $res);
            self::store($key, $entry);
        } finally {
            @unlink($lock);
        }
        return $entry;
    }

    /** Text size depends on the image resolution (font size is in points), so it is part of the key. */
    private static function key(string $label, bool $behind, array $res): string
    {
        return ($behind ? 'b' : 'n') . '|' . $res['x'] . 'x' . $res['y'] . '|' . $label;
    }

    private static function path(string $key): string
    {
        return self::$spriteDir . md5($key) . '.miff';
    }

    /** @return array{0: \Imagick, 1: int, 2: int}|null the sprite from $spriteDir, if it is there */
    private static function load(string $key): ?array
    {
        if (self::$spriteDir === null || !is_file(self::path($key))) {
            return null;
        }
        $sprite = new \Imagick(self::path($key));
        [$originX, $originY] = explode(',', $sprite->getImageProperty('comment'));
        return [$sprite, (int) $originX, (int) $originY];
    }

    /** Write the sprite to $spriteDir (whole or not at all: another job may be reading it). */
    private static function store(string $key, array $entry): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            return;
        }
        [$sprite, $originX, $originY] = $entry;
        @mkdir(self::$spriteDir, 0775, true);
        $sprite->setImageProperty('comment', "$originX,$originY");
        $sprite->setImageDepth(16);
        $tmp = $path . '.' . getmypid() . '.tmp';
        $sprite->writeImage('miff:' . $tmp);
        rename($tmp, $path);
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

        $sprite = self::outlineAndText($w, $h, $res, $lines, $behind, $originX, $originY);
        return [$sprite, $originX, $originY];
    }

    /**
     * The sprite without a stroke (see the class comment). The glyphs are
     * drawn white on black as a grey mask, with no alpha: ImageMagick 6 keeps
     * alpha inverted (as opacity) and its neighbourhood filters erode it where
     * they should grow it, while plain intensity does what it says. The mask
     * dilated and faded is the outline's alpha, the mask itself the black
     * glyphs' alpha (what the stroke pass left inside a glyph); both are given
     * to black images, laid over each other, and the white text goes on top.
     */
    private static function outlineAndText(int $w, int $h, array $res, array $lines, bool $behind, int $x, int $y): \Imagick
    {
        $mask = self::opaque($w, $h, 'black');
        $mask->setImageResolution($res['x'], $res['y']);
        $white = self::solidDraw('#ffff');
        foreach ($lines as $count => $line) {
            $mask->annotateImage($white, $x, $y + $count * self::LINE_HEIGHT, 0, $line);
        }
        $white->destroy();
        $mask->setImageAlphaChannel(\Imagick::ALPHACHANNEL_DEACTIVATE);   // annotate switches it on; CopyOpacity must read the intensity

        [$inner, $outer] = $behind ? self::HALO_BEHIND : self::HALO;
        $sprite = self::black($mask, 1, $outer);
        foreach ([self::black($mask, 0, $inner), self::black($mask, null, 1.0)] as $layer) {
            $sprite->compositeImage($layer, \Imagick::COMPOSITE_OVER, 0, 0);
            $layer->destroy();
        }
        $mask->destroy();

        $sprite->setImageFormat('png');
        $sprite->setImageResolution($res['x'], $res['y']);
        $fill = self::fillDraw($behind);
        foreach ($lines as $count => $line) {
            $sprite->annotateImage($fill, $x, $y + $count * self::LINE_HEIGHT, 0, $line);
        }
        $fill->destroy();
        return $sprite;
    }

    private static function opaque(int $w, int $h, string $color): \Imagick
    {
        $im = new \Imagick();
        $im->newImage($w, $h, new \ImagickPixel($color));
        return $im;
    }

    /**
     * A black image whose alpha is the mask grown by HALO_RADII[$reach] (the
     * mask itself for null), faded to $opacity.
     */
    private static function black(\Imagick $mask, ?int $reach, float $opacity): \Imagick
    {
        $alpha = clone $mask;
        if ($reach !== null) {
            $alpha->morphology(\Imagick::MORPHOLOGY_DILATE, 1, self::haloKernels()[$reach]);
        }
        if ($opacity < 1.0) {
            $alpha->evaluateImage(\Imagick::EVALUATE_MULTIPLY, $opacity);
        }
        $image = self::opaque($alpha->getImageWidth(), $alpha->getImageHeight(), 'black');
        $image->compositeImage($alpha, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);
        $alpha->destroy();
        return $image;
    }

    /** The discs the halo grows by, built once per process. */
    private static function haloKernels(): array
    {
        static $kernels = null;
        return $kernels ??= array_map(
            fn (float $radius) => \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_DISK, (string) $radius),
            self::HALO_RADII
        );
    }

    /** Outline then text for each line, straight onto $image: the stroked outline, for fractional positions only. */
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
        $text->setStrokeOpacity($behind ? self::OUTLINE_OPACITY_BEHIND : self::OUTLINE_OPACITY);
        return $text;
    }

    /** White text over the outline. */
    private static function fillDraw(bool $behind): \ImagickDraw
    {
        $text = self::solidDraw('#ffff');
        if ($behind) {
            $text->setFillOpacity(0.6);
        }
        return $text;
    }

    /** Filled text in one colour, no stroke. */
    private static function solidDraw(string $color): \ImagickDraw
    {
        $text = self::baseDraw();
        $text->setFillColor($color);
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
