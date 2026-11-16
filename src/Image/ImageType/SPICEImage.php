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
    /** Match the interactive SPICE layer's additive composition. */
    public function getCompositeOperator() {
        return Imagick::COMPOSITE_PLUS;
    }

    public function __construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options)
    {
        if (empty($options['compositeWCS'])) {
            parent::__construct($jp2, $filepath, $roi, $uiLabels, $offsetX, $offsetY, $options);
            return;
        }

        // Do not run the scalar subfield crop: a rotated raster may intersect
        // this viewport even when its untransformed rectangle does not.
        $this->jp2 = $jp2;
        $this->roi = $roi;
        $this->filepath = $this->outputFile = $filepath;
        $this->uiLabels = $uiLabels;
        $this->options = array_replace(array(
            'date' => '', 'layeringOrder' => 1, 'opacity' => 100,
            'jp2DifferenceLabel' => '', 'jp2Difference' => false,
        ), $options);
        $this->imageOptions = array_replace(array(
            'opacity' => 100, 'bitdepth' => 8, 'compress' => false,
            'interlace' => false,
        ), $options);

        require_once HV_ROOT_DIR.'/../src/Image/JPEG2000/JP2ImageXMLBox.php';
        $header = new Image_JPEG2000_JP2ImageXMLBox($jp2->getFilePath());
        $cd = $header->getCDMatrix();
        $sun = $header->getRefPixelCoords(); // CRPIX - inverse(CD) * CRVAL

        // Use the same 1 AU normalization as the tile metadata. The stored
        // scalar scale is |CDELT1| * DSUN/AU, not a second WCS transform.
        $factor = $jp2->getScale() / $header->getImagePlateScale() / $roi->imageScale();
        $a = $cd[0][0] * $factor;
        $b = -$cd[1][0] * $factor;
        $c = -$cd[0][1] * $factor;
        $d = $cd[1][1] * $factor;
        $shift = $options['wcsViewportOffset'] ?? array(0, 0);

        // The canonical tile grid uses refPixelX and height-refPixelY.
        // Conjugating CD by the Y flip converts FITS Y-up to raster Y-down.
        // Keep this origin convention identical to the interactive tile grid;
        // neither CROTA nor another rotation is applied after this affine.
        $tx = -$a * $sun[0] - $c * ($jp2->getHeight() - $sun[1])
            - $roi->left() / $roi->imageScale() - $shift[0];
        $ty = -$b * $sun[0] - $d * ($jp2->getHeight() - $sun[1])
            - $roi->top() / $roi->imageScale() - $shift[1];

        $region = array('left' => 0, 'top' => 0,
            'right' => $jp2->getWidth(), 'bottom' => $jp2->getHeight());
        $input = tempnam(dirname($filepath), 'spice_');
        rename($input, $input.'.pgm');
        $input .= '.pgm';
        $difference = null;
        try {
            // Decode the native raster once, preserving both pixel scales.
            $jp2->extractRegion($input, $region);
            $image = new Imagick($input);
            if ($this->options['jp2Difference'] instanceof Image_JPEG2000_JP2Image) {
                $difference = tempnam(dirname($filepath), 'spice_diff_');
                rename($difference, $difference.'.pgm');
                $difference .= '.pgm';
                $this->options['jp2Difference']->extractRegion($difference, $region);
                $previous = new Imagick($difference);
                $image = $this->runningDifference($previous, $image);
                $previous->clear();
            }
            // The grayscale source must accept RGB background layers without
            // converting their color tables to grayscale during composition.
            $image->transformImageColorspace(Imagick::COLORSPACE_SRGB);
            $this->setAlphaChannel($image);
            $image->setImageVirtualPixelMethod(Imagick::VIRTUALPIXELMETHOD_TRANSPARENT);
            $image->setImageBackgroundColor('transparent');
            $image->setImagePage(0, 0, 0, 0);
            $image->setImageArtifact('distort:viewport', sprintf('%dx%d+0+0',
                round($roi->getPixelWidth()), round($roi->getPixelHeight())));
            $image->distortImage(Imagick::DISTORTION_AFFINEPROJECTION,
                array($a, $b, $c, $d, $tx, $ty), false);
            $image->setImagePage(0, 0, 0, 0);
            $this->compressImage($image);
            $this->image = $image;
        } finally {
            unlink($input);
            if ($difference !== null) {
                unlink($difference);
            }
        }
    }
}
?>
