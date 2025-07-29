getJPXClosestToMidPoint
^^^^^^^^^^^^^^^^^^^^^^^
GET /v2/getJPXClosestToMidPoint/

Generate and (optionally) download a custom JPX movie of the specified
datasource with one frame per pair of startTimes/endTimes parameters.

.. code-block::
  :caption: Request Parameters:

    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | Parameter  | Required | Type    | Example                          | Description                                                                                                                                                                                                         |
    +============+==========+=========+==================================+=====================================================================================================================================================================================================================+
    | startTimes | Required | string  | 1306886400,1306887000,1306887600 | Comma separated Date/Time timestamps for the beginning of the JPX movie data. Date and time in Unix timestamps format separated with commas. Maximum 360 timestamps allowed due to HTTP URL length limits.          |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | endTimes   | Required | string  | 1306886700,1306887300,1306887900 | Comma separated Date/Time timestamps for the end of the JPX movie data. Date and time in Unix timestamps format separated with commas. Maximum 360 timestamps allowed due to HTTP URL length limits.                |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | sourceId   | Required | number  | 14                               | Unique image datasource identifier.                                                                                                                                                                                 |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | linked     | Optional | boolean | true                             | Generate a `linked` JPX file containing image pointers instead of data for each individual frame in the series. Currently, only JPX image series support this feature.                                              |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | verbose    | Optional | boolean | false                            | If set to `true,` the JSON response will include timestamps for each frame in the resulting movie and any warning messages associated with the request, in addition to the JPX movie file URI.                      |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | jpip       | Optional | boolean | false                            | Optionally return a JPIP URI string instead of the binary data of the movie itself, or instead of an HTTP URI in the JSON response (if `verbose` is set to `true`).                                                 |
    +------------+----------+---------+----------------------------------+---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+

Example: binary (JPX movie data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

By default, JPX binary movie data is returned. This assumes that the `jpip`
and `verbose` parameters are omitted or set to `false`.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getJPXClosestToMidPoint/?startTimes=1306886400,1306887000,1306887600&endTimes=1306886700,1306887300,1306887900&sourceId=14

Example: string (JPIP link to JPX movie)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If the `jpip` parameter is set to `true` (and the `verbose` parameter is
omitted or set to `false`) the reponse is a a JPIP-protocol link to the JPX
movie as a plain text string.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getJPXClosestToMidPoint/?startTimes=1306886400,1306887000,1306887600&endTimes=1306886700,1306887300,1306887900&sourceId=14&jpip=true

.. code-block::
    :caption: Example Response:

    jpip://api.helioviewer.org:8090/movies/SDO_AIA_335_F2011-06-01T00.00.00Z_T2011-06-01T00.25.00Z.jpx

Example: JPIP JPX Movie (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If request parameters `jpip` and `verbose` are both set to `true`, the response
is a JSON object containing a JPIP-protocol link to the JPX movie plus the
timestamps of each frame a message if the value of `cadence` was overridden by
the server.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getJPXClosestToMidPoint/?startTimes=1306886400,1306887000,1306887600&endTimes=1306886700,1306887300,1306887900&sourceId=14&jpip=true&verbose=true

.. code-block::
    :caption: Example Response:

    {
      "frames": [
        1306886547,
        [...],
        1306887735
      ],
      "message": null,
      "uri": "jpip://api.helioviewer.org:8090/movies/SDO_AIA_335_F2011-06-01T00.00.00Z_T2011-06-01T00.25.00Z.jpx"
    }

.. table:: Response Description

    +-----------+----------+--------------+--------------------------------------------------------+
    | Parameter | Required | Type         | Description                                            |
    +===========+==========+==============+========================================================+
    | message   | Required | string       | Message describing any values overrided by the server. |
    +-----------+----------+--------------+--------------------------------------------------------+
    | uri       | Required | string       | JPIP protocol link to the JPX movie.                   |
    +-----------+----------+--------------+--------------------------------------------------------+
    | frames    | Required | List[Number] | UNIX timestamps of each frame.                         |
    +-----------+----------+--------------+--------------------------------------------------------+

Example: HTTP JPX Movie (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If request parameters `verbose` is set to `true` and `jpip` is omitted or set to
`false`, the reponse is a JSON object containing an HTTP-protocol link to the
JPX movie plus the timestamps of each frame a message if the value of `cadence`
was overridden by the server.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getJPXClosestToMidPoint/?startTimes=1306886400,1306887000,1306887600&endTimes=1306886700,1306887300,1306887900&sourceId=14&verbose=true

.. code-block::
    :caption: Example Response:

    {
        "message": null,
        "uri": "http://api.helioviewer.org/jp2/movies/SDO_AIA_335_F2011-06-01T00.00.00Z_T2011-06-01T00.25.00Z.jpx.jpx",
        "frames": [
        	1306886547,
    	[...],
    	1306887735
        ]
    }

.. table:: Response

    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | Parameter | Required |     Type     |                                           Description                                           |     |
    +===========+==========+==============+=================================================================================================+=====+
    | message   | Required | string       | An informational message may be included (e.g. if the server overrode the `cadence` parameter). |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | uri       | Required | string       | JPIP protocol link to the JPX movie.                                                            |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | frames    | Optional | List[Number] | UNIX timestamps of each frame.                                                                  |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
