getClosestImage
^^^^^^^^^^^^^^^
GET /v2/getClosestImage/

Find the image data that is closest to the requested date/time. Return the
associated metadata from the helioviewer database and the XML header of the
JPEG2000 image file.

.. table:: Request Parameters:

    +-----------+----------+--------+----------------------+---------------------------------------------------------------------------------+
    | Parameter | Required |  Type  |       Example        |                                   Description                                   |
    +===========+==========+========+======================+=================================================================================+
    | date      | Required | string | 2014-01-01T23:59:59Z | Desired date/time of the image. ISO 8601 combined UTC date and time UTC format. |
    +-----------+----------+--------+----------------------+---------------------------------------------------------------------------------+
    | callback  | Optional | string |                      | Wrap the response object in a function call of your choosing.                   |
    +-----------+----------+--------+----------------------+---------------------------------------------------------------------------------+
    | sourceId  | Required | number | 14                   | Unique image datasource identifier.                                             |
    +-----------+----------+--------+----------------------+---------------------------------------------------------------------------------+

Example: Get Closest Image (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A JSON object containing metadata related to the JPEG2000 image representing the
closest temporal match for the specified datasource.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getClosestImage/?date=2014-01-01T23:59:59Z&sourceId=14

.. code-block::
    :caption: Example Response:

    {
        "id": 34205701,
        "date": "2014-01-02 00:00:02",
        "scale": 0.58996068317702,
        "width": 4096,
        "height": 4096,
        "refPixelX": 2048.5,
        "refPixelY": 2048.5,
        "sunCenterOffsetParams": [],
        "layeringOrder": 1
    }

.. table:: Response Description

    +-----------------------+----------+--------+-------------------------------------------------+
    |       Parameter       | Required |  Type  |                   Description                   |
    +=======================+==========+========+=================================================+
    | id                    | Required | number | Unique image identifier (e.g. 34205701)         |
    +-----------------------+----------+--------+-------------------------------------------------+
    | date                  | Required | string | Date/time of selected image.                    |
    +-----------------------+----------+--------+-------------------------------------------------+
    | scale                 | Required | number | Image scale in arc-seconds per pixel.           |
    +-----------------------+----------+--------+-------------------------------------------------+
    | width                 | Required | number | Width in pixels of source JPEG2000 image data.  |
    +-----------------------+----------+--------+-------------------------------------------------+
    | height                | Required | number | Height in pixels of source JPEG2000 image data. |
    +-----------------------+----------+--------+-------------------------------------------------+
    | refPixelX             | Required | number | X-coordinate of reference pixel.                |
    +-----------------------+----------+--------+-------------------------------------------------+
    | refPixelY             | Required | number | Y-coordinate of reference pixel.                |
    +-----------------------+----------+--------+-------------------------------------------------+
    | sunCenterOffsetParams | Required | string | FITS header positioning metadata.               |
    +-----------------------+----------+--------+-------------------------------------------------+
    | layeringOrder         | Required | number | Relative order for image layer compositing.     |
    +-----------------------+----------+--------+-------------------------------------------------+
