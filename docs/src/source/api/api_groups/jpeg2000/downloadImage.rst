downloadImage
^^^^^^^^^^^^^
GET /v2/downloadImage/

Download a specific jp2 img colorized and converted to a png, jpg, or webp image.

If the requested image size is larger than the underlying source image, then the
image returned will only be as large as the source image. No upscaling is performed.
For example, if you ask for width of 4k, and our source image is 1k, the result
will be a 1k image.

.. note::
    If you're looking for original jp2 images, see https://helioviewer.org/jp2/

.. table:: Request Parameters:

    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | Parameter | Required | Type    | Example              | Description                                                                                                                                                                                    |
    +===========+==========+=========+=========+=============================================================================================================================================================================================================+
    | id        | Required | number  | 7654321              | ID of the image to download                                                                                                                                                                    |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | width     | Optional | number  | 1024                 | Desired width of the resulting image. Takes priority over the 'scale' option.                                                                                                                  |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | scale     | Optional | number  | 4                    | Ratio of the size of the original image to the downloaded image. Larger numbers result in smaller sizes.                                                                                       |
    |           |          |         |                      | 1 means original size. 2 for half size. 3 for 1/3 size, etc.                                                                                                                                   |
    |           |          |         |                      | Ignored if width is provided.                                                                                                                                                                  |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | type      | Optional | string  | jpg                  | Image type. Available options are jpg, png, and webp                                                                                                                                           |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+

