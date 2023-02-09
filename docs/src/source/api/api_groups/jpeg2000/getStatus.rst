getStatus
^^^^^^^^^
GET /v2/getStatus/

Returns information about how far behind the latest available JPEG2000 images.

Example: string (JSON)
~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request

    https://api.helioviewer.org/v2/getStatus/?

.. code-block::
    :caption: Example Response:

    {
      "AIA": {
        "time": "2021-12-08T10:01:57Z",
        "level": 5,
        "secondsBehind": 1745203,
        "measurement": "AIA 171"
      },
      "COSMO": {
        "time": "2021-12-18T21:59:56Z",
        "level": 5,
        "secondsBehind": 838124,
        "measurement": "COSMO KCor"
      },
      "HMI": {
        "time": "2021-12-08T10:01:53Z",
        "level": 5,
        "secondsBehind": 1745207,
        "measurement": "HMI Int"
      },
      "LASCO": {
        "time": "2021-12-10T15:36:08Z",
        "level": 5,
        "secondsBehind": 1552352,
        "measurement": "LASCO C2"
      },
      "SECCHI": {
        "time": "2021-12-07T23:59:15Z",
        "level": 5,
        "secondsBehind": 1781365,
        "measurement": "EUVI-A 171"
      }
    }
    
        .. table:: Response Description
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | Parameter     | Required |     Type     |                                           Description                                           |     |
    +===========+==========+==============+=================================================================================================+=====+
    | time          | Required | datetime     | The timestamp of the last available measurement.                                                |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | level         | Required | Integer      | The status level of the image based on time since it was captured.                              |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | secondsBehind | Required | Integer      | Time since the image was captured in seconds.                                                   |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
    | measurement   | Required | String       | The Instrument used to capture the image.                                                       |     |
    +-----------+----------+--------------+-------------------------------------------------------------------------------------------------+-----+
