SPICE composite rendering fixture
=================================

`solo_L4_spice-n-ras_20210821T070531_67108978-000-DR6_7_0.jp2` is a real
SPICE LW O VI 103.19 nm raster, copied unchanged from the local SPICE JP2
archive. Its embedded header identifies observation 67108978, raster 000,
2021-08-21 07:05:31. This is native raster data, not a square-pixel reprojection.

SHA-256: `99a6cf74b2870cf46f12ecdfa51a3f5ba52c280d4a0901be61701874d8f85872`

Relevant native WCS values:

- Dimensions: 192 x 611 pixels.
- CUNIT1/2: deg; CDELT1: 0.0011111111111111; CDELT2: 0.000305.
- PC: [[0.927240653919, -0.102790911342], [1.3641747883, 0.927240653919]].
- CRPIX: [96.5, 303.5]; CRVAL: [-0.038375011609444, -0.026110239703361] deg.
- DSUN_OBS: 97375716447.1 m.

Reproduce from the repository root (requires Imagick and configured Kakadu):

```sh
HV_WCS_ARTIFACTS=/tmp/spice-wcs-results vendor/bin/phpunit \
  --bootstrap tests/autoload.php --testdox tests/unit_tests/image/SPICECompositeTest.php
```

The tests exercise the actual screenshot and movie-frame constructors and
compositor, with a stubbed image lookup and an isolated screenshot output path.
They require no image database writes, ingestion, or configuration changes.
The AIA control uses the existing AIA 193 fixture in `../../jpeg2000/test_data`.

The independent reference evaluates
`world = CRVAL + diag(CDELT1, CDELT2) * PC * (pixel - CRPIX)` at three
non-collinear points directly from the XML, converts degrees to arcseconds,
normalizes by DSUN/AU, and fits an affine transform through those points.
It does not use the implementation's inverse-CD solar reference pixel.
The raster Y coordinate is `height - FITS_Y`, matching the current interactive
2D tile convention (`refPixelX`, `height - refPixelY`); no extra half-pixel
origin adjustment or CROTA rotation is introduced in the server path.

Artifacts compare identical visible layers, opacity, observation, viewport and
scale. The main case contains only visible SPICE (EUI is absent because hidden),
at 100% opacity, normalized viewport X=[-500,400], screen Y=[-400,400] arcsec,
1.25 arcsec/pixel, 720 x 640 pixels. `spice-before.png` uses the unchanged
canonical/scalar tile renderer in that same viewport, exposing the old bug.
`spice-reference.png`, `spice-screenshot.png`, and `spice-frame.png` show the
forward WCS reference and corrected outputs. The cropped case uses 45% opacity,
X=[-250,150], screen Y=[-30,270], 0.8 arcsec/pixel. AIA outputs compare against
the historical screenshot and movie paths separately (their resampling differs).
These are reproducible fixture views, not a browser capture of an unavailable
attached view state. PNGs are produced only when HV_WCS_ARTIFACTS is set.

A second composition check explicitly makes AIA visible beneath 45% SPICE and
checks additive blending, matching the local interactive client's SPICE blend
rule. The main SPICE-only test does not infer missing EUI from the composition.
The additive oracle allows less than one 8-bit intensity level of mean error,
since its AIA background is already quantized; geometry-only comparisons use
mean absolute error < 0.00002 on normalized [0,1] channels.
