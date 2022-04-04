downloadMovie
^^^^^^^^^^^^^
GET /v2/downloadMovie/

Download a custom movie in one of three file formats.

.. table:: Request Parameters:

    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | Parameter | Required |  Type   | Example |                                           Description                                            |
    +===========+==========+=========+=========+==================================================================================================+
    |    id     | Required | string  |  VXvX5  |          Unique movie identifier (provided by the response to a `queueMovie` request).           |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    |  format   | Required | string  |   mp4   |                             Movie Format (`mp4`, `webm`, or `flv`).                              |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    |    hq     | Optional | boolean |  true   | Optionally download a higher-quality movie file (valid for .mp4 movies only, ignored otherwise). |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+

Example: binary (movie data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/downloadMovie/?id=VXvX5&format=mp4
