playMovie
^^^^^^^^^
GET /v2/playMovie/

Output an HTML web page with the requested movie embedded within.

.. table:: Request Parameters:

    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | Parameter | Required |  Type   | Example |                                           Description                                            |
    +===========+==========+=========+=========+==================================================================================================+
    | id        | Required | string  | VXvX5   | Unique movie identifier (provided by the response to a `queueMovie` request).                    |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | format    | Required | string  | mp4     | Movie format (mp4, webm).                                                                        |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | hq        | Optional | boolean | true    | Optionally download a higher-quality movie file (valid for .mp4 movies only, ignored otherwise). |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | width     | Optional | number  | 846     | Width of embedded movie player in pixels. Defaults to the actual width of the movie itself.      |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+
    | height    | Required | string  | 820     | Height of embedded movie player in pixels. Defaults to the actual height of the movie itself.    |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------+

Example: string (HTML)
~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/playMovie/?id=VXvX5&format=mp4&hq=true

.. code-block::
    :caption: Example Response:

    <!DOCTYPE html>
    <html>
    <head>
        <title>Helioviewer.org - 2014_02_03_20_26_16_2014_02_05_20_16_40_AIA_1600-hq.mp4</title>
        <script src="http://helioviewer.org/lib/flowplayer/flowplayer-3.2.8.min.js"></script>
    </head>
    <body>
        <!-- Movie player -->
        <div href="http://helioviewer.org/cache/movies/2014/02/05/VXvX5/2014_02_03_20_26_16_2014_02_05_20_16_40_AIA_1600-hq.mp4"
           style="display:block; width: 846px; height: 820px;"
           id="movie-player">
        </div>
        <br>
        <script language="javascript">
            flowplayer("movie-player", "http://helioviewer.org/lib/flowplayer/flowplayer-3.2.8.swf", {
                clip: {
                    autoBuffering: true,
                    scaling: "fit"
                }
            });
        </script>
    </body>
    </html>
