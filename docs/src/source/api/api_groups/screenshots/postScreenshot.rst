postScreenshot
^^^^^^^^^^^^^^

**URL:** ``/v2/postScreenshot/``

**Method:** ``POST``

**Content-Type:** ``application/json``

Generate a custom screenshot with JSON POST request.

You must specify values for either `x1`, `y1`, `x2`, and `y2`
or `x0`, `y0`, `width` and `height` inside JSON.

By default, the response is a JSON object containing a unique screenshot
identifier (`id`) that can be used to with the `downloadScreenshot` API endpoint.

Set the `display` parameter to `true` to directly return the screenshot as
binary PNG image data in the response.

Please note that each request causes the server to generate a screenshot from
scratch and is resource intensive. For performance reasons, you should cache the
response if you simply intend to serve exactly the same screenshot to multiple
users.

Request Format
~~~~~~~~~~~~~~

The request must be a JSON object with the following structure:

.. code-block:: json

       {
            "date"       : "2014-01-01T23:59:59Z",
            "imageScale" : 2.4204409,
            "layers"     : "[3,1,100]"
       }

Parameters
~~~~~~~~~~

Request JSON object consist of following parameters

.. list-table:: JSON Request Parameters:
   :header-rows: 1

   * - Parameter
     - Required
     - Type
     - Example
     - Description
   * - ``date``
     - Required
     - string
     - 2014-01-01T23:59:59Z
     - Desired date/time of the image. ISO 8601 combined UTC date and time UTC format.
   * - ``imageScale``
     - Required
     - number
     - 2.4204409
     - Image scale in arcseconds per pixel.
   * - ``layers``
     - Required
     - string
     - | [3,1,100]
       | or
       | [3,1,100,2,60,1,2010-03-01T12:12:12.000Z]
     - Image datasource layer(s) to include in the screenshot.
   * - ``eventsState``
     - Optional
     - object
     -  | {
        |    "tree_HEK": {
        |        "labels_visible": true,
        |        "layers": [
        |            {
        |                "event_type": "flare",
        |                "frms": ["frm10", "frm20"],
        |                "event_instances": ["flare--frm1--event1", "flare--frm2--event2"]
        |            }
        |        ]
        |    },
        |    ....
        | }
     - | List feature/event types and FRMs to use to annotate the screenshot. Use the empty string to indicate that no feature/event annotations should be shown.
       | To get more information about this structure, please see document : :ref:`events-state-page`
   * - ``scale``
     - Optional
     - boolean
     - false
     - Optionally overlay an image scale indicator.
   * - ``scaleType``
     - Optional
     - string
     - earth
     - Image scale indicator.
   * - ``scaleX``
     - Optional
     - number
     - -1000
     - Horizontal offset of the image scale indicator in arcseconds with respect to the center of the Sun.
   * - ``scaleY``
     - Optional
     - number
     - -500
     - Vertical offset of the image scale indicator in arcseconds with respect to the center of the Sun.
   * - ``width``
     - Optional
     - string
     - 1920
     - Width of the field of view in pixels. (Used in conjunction width `x0`,`y0`, and `height`).
   * - ``height``
     - Optional
     - string
     - 1200
     - Height of the field of view in pixels. (Used in conjunction width `x0`,`y0`, and `width`).
   * - ``x0``
     - Optional
     - string
     - 0
     - The horizontal offset of the center of the field of view from the center of the Sun. Used in conjunction with `y0`, `width`, and `height`.
   * - ``y0``
     - Optional
     - string
     - 0
     - The vertical offset of the center of the field of view from the center of the Sun. Used in conjunction with `x0`, `width`, and `height`.
   * - ``x1``
     - Optional
     - string
     - -5000
     - The horizontal offset of the top-left corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `y1`, `x2`, and `y2`.
   * - ``y1``
     - Optional
     - string
     - -5000
     - The vertical offset of the top-left corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `x2`, and `y2`.
   * - ``x2``
     - Optional
     - string
     - 5000
     - The horizontal offset of the bottom-right corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `y1`, and `y2`.
   * - ``y2``
     - Optional
     - string
     - 5000
     - The vertical offset of the bottom-right corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `y1`, and `x2`.
   * - ``display``
     - Optional
     - boolean
     - false
     - Set to `true` to directly output binary PNG image data. Default is `false` (which outputs a JSON object).
   * - ``watermark``
     - Optional
     - boolean
     - true
     - Optionally overlay a watermark consisting of a Helioviewer logo and the datasource abbreviation(s) and timestamp(s) displayed in the screenshot.
   * - ``callback``
     - Optional
     - string
     -
     - Wrap the response object in a function call of your choosing.

Example: Post Screenshot (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

JSON response to "postScreenshot" API requests. Assumes that the `display`
parameter was omitted or set to `false`.

.. code-block:: http
    :caption: Example Request:

    POST /v2/postScreenshot/ HTTP/1.1
    Host: api.helioviewer.org

    Content-Type: application/json
    {
        "date"       : "2014-01-01T23:59:59Z",
        "imageScale" : 2.4204409,
        "layers"     : "[3,1,100]"
    }

.. code-block:: json
    :caption: Example Response:

    {
        "id": 3285980
    }

.. table:: Response Description

    +-----------+----------+--------+-----------------------------------------------+
    | Parameter | Required |  Type  |                  Description                  |
    +===========+==========+========+===============================================+
    |    id     | Required | string | Unique screenshot identifier (e.g. "3285980") |
    +-----------+----------+--------+-----------------------------------------------+

Example: binary (PNG image data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Set the `display` parameter to `true` to directly return binary PNG image data
in the response.

.. code-block:: http
    :caption: Example Request:

    POST /v2/postScreenshot/ HTTP/1.1
    Host: api.Helioviewer.org

    Content-Type: application/json
    {
        "date"       : "2014-01-01T23:59:59Z",
        "imageScale" : "2.4204409",
        "layers"     : "[3,1,100]",
        "display"    : true
    }
