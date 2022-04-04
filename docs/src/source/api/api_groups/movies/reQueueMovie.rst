reQueueMovie
^^^^^^^^^^^^
GET /v2/reQueueMovie/

Re-generate a custom movie that is no longer cached on disk. Once the movie has
been successfully queued for regeneration, the Movie ID can be used to check on
the status of the movie (via `getMovieStatus <#getmoviestatus>`_) and to download the movie
(via `downloadMovie <#downloadmovie>`_).


.. table:: Request Parameters:

    +-----------+----------+--------+---------+-------------------------------------------------------------------------------+
    | Parameter | Required | Type   | Example | Description                                                                   |
    +===========+==========+========+=========+===============================================================================+
    | id        | Required | string | VXvX5   | Unique movie identifier (provided by the response to a `queueMovie` request). |
    +-----------+----------+--------+---------+-------------------------------------------------------------------------------+

Example: Queued Movie (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~

JSON response to `queueMovie <#queuemovie>`_ and `reQueueMovie <#id2>`_ API requests.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/reQueueMovie/?id=VXvX5

.. code-block::
    :caption: Example Response:

    {
        "id": "VXvX5",
        "eta": 285,
        "queue": 0,
        "token": "4673d6db4e2a3365ab361267f2a9a112"
    }

.. table:: Response Description

    +-----------+----------+--------+--------------------------------------------------------------------+
    | Parameter | Required | Type   | Description                                                        |
    +===========+==========+========+====================================================================+
    | eta       | Required | number | Estimated time until movie generation will be completed in seconds |
    +-----------+----------+--------+--------------------------------------------------------------------+
    | id        | Required | string | Unique movie identifier (e.g. "z6vX5")                             |
    +-----------+----------+--------+--------------------------------------------------------------------+
    | queue     | Required | number | Position in movie generation queue                                 |
    +-----------+----------+--------+--------------------------------------------------------------------+
    | token     | Required | string |                                                                    |
    +-----------+----------+--------+--------------------------------------------------------------------+
