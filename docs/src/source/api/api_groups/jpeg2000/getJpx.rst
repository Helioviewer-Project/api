getJPX
------
GET /v2/getJPX/

Generate and (optionally) download a custom JPX movie of the specified datasource.

.. table:: Request Parameters:

    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | Parameter | Required | Type    | Example              | Description                                                                                                                                                                                    |
    +===========+==========+=========+=========+=============================================================================================================================================================================================================+
    | startTime | Required | string  | 2014-01-01T00:00:00Z | Date/Time for the beginning of the JPX movie data. ISO 8601 combined UTC date and time UTC format.                                                                                             |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | endTime   | Required | string  | 2014-01-01T00:45:00Z | Date/Time for the end of the JPX movie data. ISO 8601 combined UTC date and time UTC format.                                                                                                   |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | sourceId  | Required | number  | 14                   | Unique image datasource identifier.                                                                                                                                                            |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | linked    | Optional | boolean | true                 | Generate a `linked` JPX file containing image pointers instead of data for each individual frame in the series. Currently, only JPX image series support this feature.                         |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | verbose   | Optional | boolean | false                | If set to `true,` the JSON response will include timestamps for each frame in the resulting movie and any warning messages associated with the request, in addition to the JPX movie file URI. |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | jpip      | Optional | boolean | false                | Optionally return a JPIP URI string instead of the binary data of the movie itself, or instead of an HTTP URI in the JSON response (if `verbose` is set to `true`).                            |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    | cadence   | Optional | number  | 12                   | The desired amount of time (in seconds) between each frame in the movie.                                                                                                                       |
    +-----------+----------+---------+----------------------+------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------+

If no cadence is specified, the server will attempt to select an optimal cadence.

Example: binary (JPX movie data)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

By default, JPX binary movie data is returned. This assumes that the `jpip`
and `verbose` parameters are omitted or set to `false`.

.. code-block::
  :caption: Example Request:

    https://api.helioviewer.org/v2/getJPX/?startTime=2014-01-01T00:00:00Z&endTime=2014-01-01T00:45:00Z&sourceId=14

Example: string (JPIP link to JPX movie)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If the `jpip` parameter is set to `true` (and the `verbose` parameter is
omitted or set to `false`) the reponse is a a JPIP-protocol link to the JPX
movie as a plain text string.

.. code-block::
  :caption: Example Request:

    https://api.helioviewer.org/v2/getJPX/?startTime=2014-01-01T00:00:00Z&endTime=2014-01-01T00:45:00Z&sourceId=14&jpip=true

.. code-block::
  :caption: Example Response:

    jpip://api.helioviewer.org:8090/movies/SDO_AIA_335_F2014-01-01T00.00.00Z_T2014-01-01T00.45.00Z.jpx


Example: JPIP JPX Movie (JSON)
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If request parameters `jpip` and `verbose` are both set to `true`, the response
is a JSON object containing a JPIP-protocol link to the JPX movie plus the
timestamps of each frame a message if the value of `cadence` was overridden by
the server.

.. code-block::
  :caption: Example Request:

    https://api.helioviewer.org/v2/getJPX/?startTime=2014-01-01T00:00:00Z&endTime=2014-01-01T00:45:00Z&sourceId=14&jpip=true&verbose=true

.. code-block::
  :caption: Example Response:

    {
      "frames": [
        1388534414,
        1388534450,
        1388534486,
        [...],
        1388537006,
        1388537042,
        1388537078
      ],
      "message": null,
      "uri": "jpip://api.helioviewer.org:8090/movies/SDO_AIA_335_F2014-01-01T00.00.00Z_T2014-01-01T00.45.00Z.jpx"
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
^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^

If request parameters `verbose` is set to `true` and `jpip` is omitted or set to
`false`, the reponse is a JSON object containing an HTTP-protocol link to the
JPX movie plus the timestamps of each frame a message if the value of `cadence`
was overridden by the server.

.. code-block::
  :caption: Example Request:

    https://api.helioviewer.org/v2/getJPX/?startTime=2014-01-01T00:00:00Z&endTime=2014-01-01T00:45:00Z&sourceId=14&verbose=true

.. code-block::
  :caption: Example Response:

    {
        "message": "Movie cadence has been changed to one image every 1284 seconds in order to avoid exceeding the maximum allowed number of frames (1000) between the requested start and end dates.",
        "uri": "http://api.helioviewer.org/jp2/movies/SOHO_MDI_MDI_magnetogram_F2003-10-05T00.00.00Z_T2003-10-20T00.00.00ZB1L.jpx",
        "frames": [
            1065323703,
            1065329463,
            1065335223,
            [...],
            1066596483,
            1066602183,
            1066607943
        ]
    }

.. table:: Response Description

    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+
    | Parameter | Required | Type         | Description                                                                                     |
    +===========+==========+==============+=================================================================================================+
    | message   | Required | string       | An informational message may be included (e.g. if the server overrode the `cadence` parameter). |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+
    | uri       | Required | string       | JPIP protocol link to the JPX movie.                                                            |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+
    | frames    | Optional | List[Number] | UNIX timestamps of each frame.                                                                  |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+
