takeScreenshot
^^^^^^^^^^^^^^
GET /v2/takeScreenshot/

Generate a custom screenshot.

You must specify values for either `x1`, `y1`, `x2`, and `y2`
or `x0`, `y0`, `width` and `height`.

By default, the response is a JSON object containing a unique screenshot
identifier (`id`) that can be used to with the `downloadScreenshot` API endpoint.

Set the `display` parameter to `true` to directly return the screenshot as
binary PNG image data in the response.

Please note that each request causes the server to generate a screenshot from
scratch and is resource intensive. For performance reasons, you should cache the
response if you simply intend to serve exactly the same screenshot to multiple
users.

.. table:: Request Parameters:

    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    |  Parameter  | Required |  Type   |                        Example                         |                                                                                 Description                                                                                 |
    +=============+==========+=========+========================================================+=============================================================================================================================================================================+
    | date        | Required | string  | 2014-01-01T23:59:59Z                                   | Desired date/time of the image. ISO 8601 combined UTC date and time UTC format.                                                                                             |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | imageScale  | Required | number  | 2.4204409                                              | Image scale in arcseconds per pixel.                                                                                                                                        |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | layers      | Required | string  | | [3,1,100]                                            |                                                                                                                                                                             |
    |             |          |         | | or                                                   |                                                                                                                                                                             |
    |             |          |         | | [3,1,100,2,60,1,2010-03-01T12:12:12.000Z]            | Image datasource layer(s) to include in the screenshot.                                                                                                                     |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | events      | Optional | string  | [AR,HMI_HARP;SPoCA,1],[CH,all,1]                       | List feature/event types and FRMs to use to annoate the movie. Use the empty string to indicate that no feature/event annotations should be shown.                          |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | eventLabels | Required | boolean | false                                                  | Optionally annotate each event marker with a text label.                                                                                                                    |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | scale       | Optional | boolean | false                                                  | Optionally overlay an image scale indicator.                                                                                                                                |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | scaleType   | Optional | string  | earth                                                  | Image scale indicator.                                                                                                                                                      |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | scaleX      | Optional | number  | -1000                                                  | Horizontal offset of the image scale indicator in arcseconds with respect to the center of the Sun.                                                                         |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | scaleY      | Optional | number  | -500                                                   | Vertical offset of the image scale indicator in arcseconds with respect to the center of the Sun.                                                                           |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | width       | Optional | string  | 1920                                                   | Width of the field of view in pixels. (Used in conjunction width `x0`,`y0`, and `height`).                                                                                  |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | height      | Optional | string  | 1200                                                   | Height of the field of view in pixels. (Used in conjunction width `x0`,`y0`, and `width`).                                                                                  |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | x0          | Optional | string  | 0                                                      | The horizontal offset of the center of the field of view from the center of the Sun. Used in conjunction with `y0`, `width`, and `height`.                                  |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | y0          | Optional | string  | 0                                                      | The vertical offset of the center of the field of view from the center of the Sun. Used in conjunction with `x0`, `width`, and `height`.                                    |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | x1          | Optional | string  | -5000                                                  | The horizontal offset of the top-left corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `y1`, `x2`, and `y2`.     |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | y1          | Optional | string  | -5000                                                  | The vertical offset of the top-left corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `x2`, and `y2`.       |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | x2          | Optional | string  | 5000                                                   | The horizontal offset of the bottom-right corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `y1`, and `y2`. |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | y2          | Optional | string  | 5000                                                   | The vertical offset of the bottom-right corner of the field of view with respect to the center of the Sun (in arcseconds). Used in conjunction with `x1`, `y1`, and `x2`.   |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | display     | Optional | boolean | false                                                  | Set to `true` to directly output binary PNG image data. Default is `false` (which outputs a JSON object).                                                                   |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | watermark   | Optional | boolean | true                                                   | Optionally overlay a watermark consisting of a Helioviewer logo and the datasource abbreviation(s) and timestamp(s) displayed in the screenshot.                            |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | callback    | Optional | string  |                                                        | Wrap the response object in a function call of your choosing.                                                                                                               |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+


Example: Take Screenshot (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

JSON response to "takeScreenshot" API requests. Assumes that the `display`
parameter was omitted or set to `false`.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/takeScreenshot/?date=2014-01-01T23:59:59Z&imageScale=2.4204409&layers=[SDO,AIA,AIA,335,1,100]&events=[AR,HMI_HARP;SPoCA,1],[CH,all,1]&eventsLabels=false&x0=0&y0=0&width=1920&height=1200

.. code-block::
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

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/takeScreenshot/?imageScale=2.4204409&layers=[SDO,AIA,AIA,304,1,100]&events=&eventLabels=true&scale=true&scaleType=earth&scaleX=0&scaleY=0&date=2014-02-25T15:53:00.136Z&x1=-929.2475775696686&x2=106.70112763033143&y1=-970.7984919973343&y2=486.3069298026657&display=true&watermark=true&events=[CH,all,1]
