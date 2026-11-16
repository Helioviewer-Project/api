<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * Builds the event-region layer as a single SVG document, mirroring the web
 * client (reports/region-drawing-spec.html section 9): a flat far-side TINT group
 * at opacity 0.18 (each event's tints cut out from under its own fills so the
 * translucent layers never stack darker), the dashed far-side GHOST lines (at 45%
 * strength inside their own fills), then the near-side FILLS.
 *
 * The cut-outs are clip paths, not masks: librsvg renders every masked group on
 * its own full-canvas surface, which made masks ~80% of the rasterise time on
 * busy frames. A clip only involves the event's fill polygons whose bounding box
 * touches the tints or ghosts being clipped; the others cannot change a pixel.
 *
 * The document covers only area(): the part of the frame the shapes are drawn
 * on, on whole pixels, through a viewBox. Rasterising and compositing an area
 * instead of the full frame gives the same pixels (a whole-pixel shift does not
 * change antialiasing) and was ~15% of the region time on busy frames.
 *
 * Input is per-event shapes already projected to frame pixels; output is the SVG
 * string, which SvgRasterizer turns into a PNG. Kept out of the image compositor
 * so the paint recipe is a self-contained, testable object.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class EventRegionSvg
{
    /** Ghost-line opacity outside the event's fills. */
    private const GHOST_OPACITY = 0.55;

    /** Ghost-line opacity inside them: 45% strength (0.55 * 115/255, the old #737373 mask). */
    private const GHOST_INSIDE_OPACITY = 0.248;

    /** Bounding-box padding (px) for the touch test: half the 1.5 px stroke plus antialiasing, with margin. */
    private const TOUCH_PAD = 3.0;

    /** Margin (px) around the drawn area: half the 1.5 px stroke, round joins and caps, antialiasing, with margin. */
    private const AREA_PAD = 3.0;

    /**
     * @param array<int, array{hex: string, tints: array, ghosts: array, fills: array}> $rendered
     *        Per event: a tint/fill is a list of {x,y} pixel polygons; a ghost is
     *        {pts: [{x,y}...], closed: bool}. Colour is a 6-hex string, no '#'.
     */
    public function __construct(
        private array $rendered,
        private int $width,
        private int $height
    ) {
    }

    /**
     * The part of the frame the shapes are drawn on, as whole pixels
     * [x, y, width, height] inside the frame, or null when nothing is drawn on it.
     * Rasterise toSvg() at this width and height and place it at (x, y).
     */
    public function area(): ?array
    {
        $polys = [];
        foreach ($this->rendered as $ed) {
            array_push($polys, ...$ed['tints'], ...$ed['fills'], ...array_column($ed['ghosts'], 'pts'));
        }
        $box = self::bbox($polys);
        if ($box === null) return null;
        $x0 = max(0, (int) floor($box[0] + self::TOUCH_PAD - self::AREA_PAD));
        $y0 = max(0, (int) floor($box[1] + self::TOUCH_PAD - self::AREA_PAD));
        $x1 = min($this->width, (int) ceil($box[2] - self::TOUCH_PAD + self::AREA_PAD));
        $y1 = min($this->height, (int) ceil($box[3] - self::TOUCH_PAD + self::AREA_PAD));
        if ($x1 <= $x0 || $y1 <= $y0) return null;
        return [$x0, $y0, $x1 - $x0, $y1 - $y0];
    }

    /** The complete SVG document as a string, covering area() (the full frame when nothing is drawn). */
    public function toSvg(): string
    {
        $defs = ''; $tintG = ''; $ghostG = ''; $fillG = '';

        foreach ($this->rendered as $i => $ed) {
            $hex = $ed['hex'];
            $fills = $ed['fills'];

            // Which of the event's fill polygons can touch its tints / its ghosts.
            $fillBoxes = array_map(fn (array $poly) => self::bbox([$poly]), $fills);
            $tintCuts  = self::touching($fillBoxes, self::bbox($ed['tints']));
            $ghostCuts = self::touching($fillBoxes, self::bbox(array_column($ed['ghosts'], 'pts')));

            // "Outside fill k": the canvas with polygon k cut out (even-odd). Being
            // outside all of an event's fills is these clips nested, so fill
            // polygons that overlap each other are still cut out exactly once.
            foreach (array_unique(array_merge($tintCuts, $ghostCuts)) as $k) {
                $defs .= '<clipPath id="o' . $i . '_' . $k . '"><path clip-rule="evenodd" d="M0 0H' . $this->width
                       . 'V' . $this->height . 'H0Z M' . self::points($fills[$k]) . 'Z"/></clipPath>';
            }

            if (!empty($ed['tints'])) {
                $in = '';
                foreach ($ed['tints'] as $poly) $in .= '<polygon points="' . self::points($poly) . '" fill="#' . $hex . '"/>';
                $tintG .= self::outside($in, $i, $tintCuts);
            }

            if (!empty($ed['ghosts'])) {
                if (empty($ghostCuts)) {
                    $ghostG .= self::ghostLines($ed['ghosts'], $hex, self::GHOST_OPACITY);
                } else {
                    // Full strength outside the fills, 45% strength inside them.
                    $inside = '';
                    foreach ($ghostCuts as $k) $inside .= '<polygon points="' . self::points($fills[$k]) . '"/>';
                    $defs .= '<clipPath id="in' . $i . '">' . $inside . '</clipPath>';
                    $ghostG .= self::outside(self::ghostLines($ed['ghosts'], $hex, self::GHOST_OPACITY), $i, $ghostCuts)
                             . '<g clip-path="url(#in' . $i . ')">' . self::ghostLines($ed['ghosts'], $hex, self::GHOST_INSIDE_OPACITY) . '</g>';
                }
            }

            foreach ($fills as $poly) {
                $fillG .= '<polygon points="' . self::points($poly) . '" fill="#' . $hex
                        . '" fill-opacity="0.4" stroke="#000000" stroke-opacity="0.533" stroke-width="1.5" stroke-linejoin="round"/>';
            }
        }

        [$x, $y, $w, $h] = $this->area() ?? [0, 0, $this->width, $this->height];
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $w . '" height="' . $h . '" viewBox="' . $x . ' ' . $y . ' ' . $w . ' ' . $h . '">'
             . '<defs>' . $defs . '</defs>'
             . '<g opacity="0.18">' . $tintG . '</g>'   // flat tint layer: overlaps stay 0.18
             . '<g>' . $ghostG . '</g>'
             . '<g>' . $fillG . '</g>'
             . '</svg>';
    }

    /** The dashed ghost runs of one event at the given stroke opacity. */
    private static function ghostLines(array $ghosts, string $hex, float $opacity): string
    {
        $out = '';
        foreach ($ghosts as $gs) {
            // Closed contour draws as a polygon (needs >=3 points); otherwise a polyline.
            $tag = ($gs['closed'] && count($gs['pts']) >= 3) ? 'polygon' : 'polyline';
            $out .= '<' . $tag . ' points="' . self::points($gs['pts']) . '" fill="none" stroke="#' . $hex
                  . '" stroke-opacity="' . $opacity . '" stroke-width="1.5" stroke-dasharray="5,4" stroke-linejoin="round" stroke-linecap="round"/>';
        }
        return $out;
    }

    /** $content clipped to outside each of event $i's fill polygons $cuts (nested groups). */
    private static function outside(string $content, int $i, array $cuts): string
    {
        foreach ($cuts as $k) $content = '<g clip-path="url(#o' . $i . '_' . $k . ')">' . $content . '</g>';
        return $content;
    }

    /** Indexes of the boxes in $boxes that overlap $box. */
    private static function touching(array $boxes, ?array $box): array
    {
        if ($box === null) return [];
        $out = [];
        foreach ($boxes as $k => $b) {
            if ($b !== null && $b[0] < $box[2] && $box[0] < $b[2] && $b[1] < $box[3] && $box[1] < $b[3]) $out[] = $k;
        }
        return $out;
    }

    /** Padded [x0, y0, x1, y1] around all points of $polys, or null if there are none. */
    private static function bbox(array $polys): ?array
    {
        $x0 = INF; $y0 = INF; $x1 = -INF; $y1 = -INF;
        foreach ($polys as $poly) {
            foreach ($poly as $p) {
                $x0 = min($x0, $p['x']); $y0 = min($y0, $p['y']);
                $x1 = max($x1, $p['x']); $y1 = max($y1, $p['y']);
            }
        }
        if ($x0 === INF) return null;
        return [$x0 - self::TOUCH_PAD, $y0 - self::TOUCH_PAD, $x1 + self::TOUCH_PAD, $y1 + self::TOUCH_PAD];
    }

    /** "x,y x,y …" for one polygon/polyline. */
    private static function points(array $poly): string
    {
        $s = '';
        foreach ($poly as $p) $s .= round($p['x'], 1) . ',' . round($p['y'], 1) . ' ';
        return rtrim($s);
    }
}
