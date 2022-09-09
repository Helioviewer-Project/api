getMovieStatus
^^^^^^^^^^^^^^
GET /v2/getMovieStatus/

.. table:: Request Parameters:

    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+
    | Parameter | Required |  Type   | Example |                                  Description                                   |
    +===========+==========+=========+=========+================================================================================+
    | id        | Required | string  | VXvX5   | Unique movie identifier (provided by the response to a `queueMovie` request).  |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+
    | format    | Required | string  | mp4     | Movie format (`mp4`, `webm`, or `flv`).                                        |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+
    | verbose   | Optional | boolean | true    | Optionally include extra metadata in the response.                             |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+
    | callback  | Optional | string  |         | Wrap the response object in a function call of your choosing.                  |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+
    | token     | Optional | string  |         | Handle to job in the movie builder queue.                                      |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------+

Example: Movie Status (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A JSON object containing metadata describing a user-generated movie, including
its generation status (0=queued, 1=processing, 2=finished, 3=invalid).

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getMovieStatus/?id=VXvX5&format=mp4

.. code-block::
    :caption: Example Response:

    {
      "frameRate": 15,
      "numFrames": 300,
      "startDate": "2014-02-03 20:26:16",
      "status": 2,
      "endDate": "2014-02-05 20:16:40",
      "width": 846,
      "height": 820,
      "title": "SDO AIA AIA 1600 (2014-02-03 20:26:16 - 20:16:40 UTC)",
      "thumbnails": {
        "icon": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-icon.png",
        "small": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-small.png",
        "medium": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-medium.png",
        "large": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-large.png",
        "full": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-full.png"
      },
      "url": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/2014_02_03_20_26_16_2014_02_05_20_16_40_.mp4",
      "statusLabel": "Completed"
    }

.. table:: Response Description

    +-----------+----------+--------+-------------------------------------------------------------------------+
    | Parameter | Required |  Type  |                               Description                               |
    +===========+==========+========+=========================================================================+
    |  status   | Required | number | | Movie generation status                                               |
    |           |          |        | | (0=queued, 1=processing, 2=finished, 3=invalid)                       |
    +-----------+----------+--------+-------------------------------------------------------------------------+

Example: Movie Status Verbose (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

A JSON object containing metadata describing a user-generated movie, including
its generation status (0=queued, 1=processing, 2=finished, 3=invalid) and the
parameters used to create it.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getMovieStatus/?id=VXvX5&format=webm&verbose=true&callback=callbackTest

.. code-block::
    :caption: Example Response:

    callbackTest({
      "frameRate": 15,
      "numFrames": 300,
      "startDate": "2014-02-03 20:26:16",
      "status": 2,
      "endDate": "2014-02-05 20:16:40",
      "width": 846,
      "height": 820,
      "title": "SDO AIA AIA 1600 (2014-02-03 20:26:16 - 20:16:40 UTC)",
      "thumbnails": {
        "icon": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-icon.png",
        "small": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-small.png",
        "medium": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-medium.png",
        "large": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-large.png",
        "full": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/preview-full.png"
      },
      "url": "http://api.helioviewer.org/cache/movies/2014/02/05/VXvX5/2014_02_03_20_26_16_2014_02_05_20_16_40_.webm",
      "timestamp": "2014-02-05 20:25:55",
      "duration": 20,
      "imageScale": 2.42044,
      "layers": "[SDO,AIA,AIA,1600,1,100]",
      "events": "[AR,all,1],[CC,all,1],[CD,all,1],[CH,all,1],[CJ,all,1],[CE,all,1],[CR,all,1],[CW,all,1],[EF,all,1],[ER,all,1],[FI,all,1],[FA,all,1],[FE,all,1],[FL,all,1],[LP,all,1],[OS,all,1],[PG,all,1],[SG,all,1],[SP,all,1],[SS,all,1]",
      "x1": -1011.7442962,
      "y1": -980.2785645,
      "x2": 1035.9487052,
      "y2": 1004.4829735,
      "statusLabel": "Completed"
    })

.. table:: Response Description

    +------------+----------+--------+---------------------------------------------------------------------------+
    | Parameter  | Required | Type   | Description                                                               |
    +============+==========+========+===========================================================================+
    | endDate    | Optional | string | Date of final frame in movie (e.g. "2014-02-05 19:40:16")                 |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | frameRate  | Optional | number | Number of frames per second (e.g. 15)                                     |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | height     | Optional | number | Pixel height of movie                                                     |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | numFrames  | Optional | number | Total number of frames (e.g. 300)                                         |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | startDate  | Optional | string | Date of first frame in movie (e.g. "2014-02-05 19:40:16")                 |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | status     | Required | number | Movie generation status (0=queued, 1=processing, 2=finished, 3=invalid)   |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | title      | Optional | string | Title of movie (e.g. "SDO AIA 1600 (2014-02-03 20:26:16 - 19:40:16 UTC)") |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | url        | Optional | string | Download URL for movie in specified format                                |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | width      | Optional | number | Pixel width of movie                                                      |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | duration   | Optional | string | Movie duration in seconds (e.g. 20)                                       |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | events     | Optional | string | Solar Feature/Event layer(s) contained in movie                           |
    | imageScale | Optional | number | Image scale in arcseconds per pixel (e.g. 2.42044)                        |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | layers     | Optional | string | Image datasource layer(s) contained in movie                              |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | timestamp  | Optional | string | Timestamp (e.g. "2014-02-05 20:25:55")                                    |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | x1         | Optional | number | Arc seconds from Sun center (e.g. -1011.7442962)                          |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | y1         | Optional | number | Arc seconds from Sun center (e.g. -980.2785645)                           |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | x2         | Optional | number | Arc seconds from Sun center (e.g. 1035.9487052)                           |
    +------------+----------+--------+---------------------------------------------------------------------------+
    | y2         | Optional | number | Arc seconds from Sun center (e.g. 1004.4829735)                           |
    +------------+----------+--------+---------------------------------------------------------------------------+
