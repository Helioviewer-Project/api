getTile
^^^^^^^
GET /v2/getTile/

Request a single image tile to be used in the Helioviewer.org Viewport. Tiles
are 512x512 pixel PNG images, generated for a given image scale from the
intermediary JPEG2000 image files.

Use the `getClosestImage` API endpoint to obtain the desired image identifier
for the `id` parameter.

.. table:: Request Parameters:

    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    |  Parameter   | Required |  Type  |  Example   |                                                      Description                                                      |
    +==============+==========+========+============+=======================================================================================================================+
    | id           | Required | number | 36275490   | Unique image identifier.                                                                                              |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | x            | Required | number | -1         | Tile position.                                                                                                        |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | y            | Required | number | -1         | Tile position.                                                                                                        |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | imageScale   | Required | number | 2.42044088 | Image scale in arcseconds per pixel.                                                                                  |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | difference   | Required | number | 0          | | Specify image type difference.                                                                                      |
    |              |          |        |            | | 0 - Display regular image;                                                                                          |
    |              |          |        |            | | 0 - Running difference image;                                                                                       |
    |              |          |        |            | | 0 - Base difference image.                                                                                          |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | diffCount    | Required | number | 60         | Used to display Running difference image. Work with "diffTime" parameter and set amount of time to use in tyme period |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | diffTime     | Required | number | 1          | | Select Running difference time period:                                                                              |
    |              |          |        |            | | 1 - Minutes;                                                                                                        |
    |              |          |        |            | | 2 - Hours;                                                                                                          |
    |              |          |        |            | | 3 - Days;                                                                                                           |
    |              |          |        |            | | 4 - Weeks;                                                                                                          |
    |              |          |        |            | | 5 - Month;                                                                                                          |
    |              |          |        |            | | 6 - Years.                                                                                                          |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+
    | baseDiffTime | Required | number | string     | Date/Time string for Base difference images.                                                                          |
    +--------------+----------+--------+------------+-----------------------------------------------------------------------------------------------------------------------+

Example: binary (PNG image data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getTile/?id=36275490&x=-1&y=-1&imageScale=2.42044088
