postMovie
^^^^^^^^^

**URL:** ``/v2/postMovie/``

**Method:** ``POST``

**Content-Type:** ``application/json``

Create a custom movie with a POST request by submitting JSON structure to the movie generation queue.
The response returned will provide you with a unique Movie ID that can be used
to check on the status of your movie (via `getMovieStatus <#getmoviestatus>`_)
and to download your movie (via `downloadMovie <#downloadmovie>`_).

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
   * - ``startTime``
     - Required
     - string
     - 2010-03-01T12:12:12Z
     - Desired date and time of the first frame of the movie. ISO 8601 combined UTC date and time UTC format.
   * - ``endTime``
     - Required
     - string
     - 2010-03-04T12:12:12Z
     - Desired date and time of the final frame of the movie. ISO 8601 combined UTC date and time UTC format.
   * - ``layers``
     - Required
     - string
     - | [3,1,100]
       | or
       | [3,1,100,2,60,1,2010-03-01T12:12:12.000Z]
     - Image datasource layer(s) to include in the movie.
   * - ``eventsState``
     - Optional
     - object
     - | {
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
     - | List feature/event types and FRMs to use to annotate the movie. Use the empty string to indicate that no feature/event annotations should be shown.
       | To get more information about this structure, please see document : :ref:`events-state-page`
   * - ``imageScale``
     - Required
     - number
     - 21.04
     - Image scale in arcseconds per pixel.
   * - ``format``
     - Optional
     - string
     - mp4
     - Movie format (`mp4`, `webm`, `flv`). Default value is `mp4`.
   * - ``frameRate``
     - Optional
     - string
     - 15
     - Movie frames per second. 15 frames per second by default.
   * - ``maxFrames``
     - Optional
     - string
     - 300
     - Maximum number of frames in the movie. May be capped by the server.
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
   * - ``movieLength``
     - Optional
     - number
     - 4.3333
     - Movie length in seconds.
   * - ``watermark``
     - Optional
     - boolean
     - true
     - Optionally overlay a Helioviewer.org watermark image. Enabled by default.
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
   * - ``callback``
     - Optional
     - string
     -
     - Wrap the response object in a function call of your choosing.
   * - ``size``
     - Optional
     - number
     - 0
     - | Scale video to preset size
       | 0 - Original size
       | 1 - 720p (1280 x 720, HD Ready);
       | 2 - 1080p (1920 x 1080, Full HD);
       | 3 - 1440p (2560 x 1440, Quad HD);
       | 4 - 2160p (3840 x 2160, 4K or Ultra HD).
   * - ``movieIcons``
     - Optional
     - number
     - 0
     - Display other user generated movies on the video.
   * - ``followViewport``
     - Optional
     - number
     - 0
     - Rotate field of view of movie with Sun.
   * - ``reqObservationDate``
     - Optional
     - string
     - 2017-08-30T14:45:53.000Z
     - Viewport time. Used when 'followViewport' enabled to shift viewport area to correct coordinates.

Example: Queued Movie (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

JSON response to "postMovie" API requests.

.. code-block:: http
    :caption: Example Request:

    POST /v2/postMovie/ HTTP/1.1
    Host: api.helioviewer.org

    Content-Type: application/json
    {
        "startTime"       : "2010-03-01T12:12:12Z",
        "endTime"       : "2010-03-04T12:12:12Z",
        "imageScale" : 21.04,
        "layers"     : "[3,1,100]",
        "eventsState" : {
           "tree_HEK": {
               "labels_visible": true,
               "layers": [
                   {
                       "event_type": "flare",
                       "frms": ["frm10", "frm20"],
                       "event_instances": ["flare--frm1--event1", "flare--frm2--event2"]
                   }
               ]
           },
        },
        "x1" : -5000,
        "y1" : -5000,
        "x2" : 5000,
        "y2" : 5000,
    }

.. code-block:: json
    :caption: Example Response:

    {
      "id": "z6vX5",
      "eta": 376,
      "queue": 0,
      "token": "50e0d98f645b42d159ec1c8a1e15de3e"
    }

.. list-table:: JSON Response Parameters:
   :header-rows: 1

   * - Parameter
     - Required
     - Type
     - Description
   * - ``id``
     - Required
     - string
     - Unique movie identifier (e.g. "z6vX5")
   * - ``eta``
     - Required
     - number
     - Estimated time until movie generation will be completed in seconds
   * - ``queue``
     - Required
     - number
     - Position in movie generation queue
   * - ``token``
     - Required
     - string
     - Handle to job in the movie builder queue

