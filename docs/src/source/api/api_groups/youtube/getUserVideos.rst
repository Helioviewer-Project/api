getUserVideos
^^^^^^^^^^^^^
GET /v2/getUserVideos/

Get a listing (in descending time order) of the most recently user-generated
movies that have been publicly shared to YouTube. Result set is limited to the
value requested or default value of the `num` parameter (unless truncated when
the date value of the `since` parameter is reached).

.. table:: Request Parameters:

    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------------------+
    | Parameter | Required |  Type   | Example |                                                 Description                                                  |
    +===========+==========+=========+=========+==============================================================================================================+
    | num       | Optional | number  | 10      | Number of shared user-generated movies to include in the response. Default is 10.                            |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------------------+
    | since     | Optional | string  |         | Optionally truncate result set if this date/time is reached. ISO 8601 combined UTC date and time UTC format. |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------------------+
    | force     | Optional | boolean | false   | Optionally bypass cache to retrieve most up-to-date data.                                                    |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------------------+
    | callback  | Optional | string  |         | Wrap the response object in a function call of your choosing.                                                |
    +-----------+----------+---------+---------+--------------------------------------------------------------------------------------------------------------+

Example: string (JSON)
~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getUserVideos/?num=2

.. code-block::
    :caption: Example Response:

    [
        {
            "id": "J5tX5",
            "url": "http://www.youtube.com/watch?v=x2VYjI1Eo3M",
            "thumbnails": {
                "icon": "http://api.helioviewer.org/cache/movies/2014/02/27/J5tX5/preview-icon.png",
                "small": "http://api.helioviewer.org/cache/movies/2014/02/27/J5tX5/preview-small.png",
                "medium": "http://api.helioviewer.org/cache/movies/2014/02/27/J5tX5/preview-medium.png",
                "large": "http://api.helioviewer.org/cache/movies/2014/02/27/J5tX5/preview-large.png",
                "full": "http://api.helioviewer.org/cache/movies/2014/02/27/J5tX5/preview-full.png"
            },
            "published": "2014-02-27 14:56:00"
        },
        {
            "id": "L5tX5",
            "url": "http://www.youtube.com/watch?v=h6Y6vhpKPRk",
            "thumbnails": {
                "icon": "http://api.helioviewer.org/cache/movies/2014/02/27/L5tX5/preview-icon.png",
                "small": "http://api.helioviewer.org/cache/movies/2014/02/27/L5tX5/preview-small.png",
                "medium": "http://api.helioviewer.org/cache/movies/2014/02/27/L5tX5/preview-medium.png",
                "large": "http://api.helioviewer.org/cache/movies/2014/02/27/L5tX5/preview-large.png",
                "full": "http://api.helioviewer.org/cache/movies/2014/02/27/L5tX5/preview-full.png"
            },
            "published": "2014-02-27 14:54:08"
        }
    ]
