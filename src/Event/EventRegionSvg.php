<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * Builds the event-region layer as a single SVG document, mirroring the web
 * client (reports/region-drawing-spec.html section 9): a flat far-side TINT group
 * at opacity 0.18 (each event's tints masked out from under its own fills so the
 * translucent layers never stack darker), the dashed far-side GHOST lines (masked
 * to 45% strength inside their own fills), then the near-side FILLS.
 *
 * Input is per-event shapes already projected to frame pixels; output is the SVG
 * string, which SvgRasterizer turns into a PNG. Kept out of the image compositor
 * so the paint recipe is a self-contained, testable object.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class EventRegionSvg
{
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

    /** The complete SVG document as a string. */
    public function toSvg(): string
    {
        $maskRegion = 'maskUnits="userSpaceOnUse" x="0" y="0" width="' . $this->width . '" height="' . $this->height . '"';
        $defs = ''; $tintG = ''; $ghostG = ''; $fillG = '';

        foreach ($this->rendered as $i => $ed) {
            $hex = $ed['hex'];
            $hasFill = !empty($ed['fills']);

            if ($hasFill) {
                // Two luminance masks over the event's own fills: black cuts the tint,
                // 45% grey dims the ghost to 0.45 of its strength (0.55 * 0.45 ~= 0.25).
                $black = ''; $grey = '';
                foreach ($ed['fills'] as $poly) {
                    $pp = self::points($poly);
                    $black .= '<polygon points="' . $pp . '" fill="#000000"/>';
                    $grey  .= '<polygon points="' . $pp . '" fill="#737373"/>';
                }
                $defs .= '<mask id="tc' . $i . '" ' . $maskRegion . '><rect width="' . $this->width . '" height="' . $this->height . '" fill="#ffffff"/>' . $black . '</mask>'
                       . '<mask id="gd' . $i . '" ' . $maskRegion . '><rect width="' . $this->width . '" height="' . $this->height . '" fill="#ffffff"/>' . $grey . '</mask>';
            }

            if (!empty($ed['tints'])) {
                $in = '';
                foreach ($ed['tints'] as $poly) $in .= '<polygon points="' . self::points($poly) . '" fill="#' . $hex . '"/>';
                $tintG .= $hasFill ? '<g mask="url(#tc' . $i . ')">' . $in . '</g>' : $in;
            }

            if (!empty($ed['ghosts'])) {
                $in = '';
                foreach ($ed['ghosts'] as $gs) {
                    // Closed contour draws as a polygon (needs >=3 points); otherwise a polyline.
                    $tag = ($gs['closed'] && count($gs['pts']) >= 3) ? 'polygon' : 'polyline';
                    $in .= '<' . $tag . ' points="' . self::points($gs['pts']) . '" fill="none" stroke="#' . $hex
                         . '" stroke-opacity="0.55" stroke-width="1.5" stroke-dasharray="5,4" stroke-linejoin="round" stroke-linecap="round"/>';
                }
                $ghostG .= $hasFill ? '<g mask="url(#gd' . $i . ')">' . $in . '</g>' : $in;
            }

            if ($hasFill) {
                foreach ($ed['fills'] as $poly) {
                    $fillG .= '<polygon points="' . self::points($poly) . '" fill="#' . $hex
                            . '" fill-opacity="0.4" stroke="#000000" stroke-opacity="0.533" stroke-width="1.5" stroke-linejoin="round"/>';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $this->width . '" height="' . $this->height . '">'
             . '<defs>' . $defs . '</defs>'
             . '<g opacity="0.18">' . $tintG . '</g>'   // flat tint layer: overlaps stay 0.18
             . '<g>' . $ghostG . '</g>'
             . '<g>' . $fillG . '</g>'
             . '</svg>';
    }

    /** "x,y x,y …" for one polygon/polyline. */
    private static function points(array $poly): string
    {
        $s = '';
        foreach ($poly as $p) $s .= round($p['x'], 1) . ',' . round($p['y'], 1) . ' ';
        return rtrim($s);
    }
}
