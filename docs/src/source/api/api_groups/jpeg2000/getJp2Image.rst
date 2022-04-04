getJP2Image
^^^^^^^^^^^
GET /v2/getJP2Image

Download a JP2 image for the specified datasource that is the closest match in
time to the \`date\` requested.

.. table:: `Request Parameters:`

    +-----------+----------+---------+----------------------+-------------------------------------------------------------------------------------+
    | Parameter | Required | Type    | Example              | Description                                                                         |
    +===========+==========+=========+======================+=====================================================================================+
    | date      | Required | string  | 2014-01-01T23:59:59Z | Desired date/time of the JP2 image. ISO 8601 combined UTC date and time UTC format. |
    +-----------+----------+---------+----------------------+-------------------------------------------------------------------------------------+
    | sourceId  | Required | number  | 14                   | Unique image datasource identifier.                                                 |
    +-----------+----------+---------+----------------------+-------------------------------------------------------------------------------------+
    | jpip      | Optional | boolean | false                | Optionally return a JPIP URI instead of the binary data of the image itself.        |
    +-----------+----------+---------+----------------------+-------------------------------------------------------------------------------------+
    | json      | Optional | boolean | false                | Optionally return a JSON object.                                                    |
    +-----------+----------+---------+----------------------+-------------------------------------------------------------------------------------+

Example: binary (JPEG2000 image data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~
By default, JPEG2000 binary image data is returned. This assumes that the `jpip`
parameter is omitted or set to `false`.

.. code-block::
    :caption: example

    https://api.helioviewer.org/v2/getJP2Image/?date=2014-01-01T23:59:59Z&sourceId=14

Example: string (JPIP link to JP2 Image)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If request parameter `jpip` is set to `true` (and the `json` parameter is
omitted or set to `false`) the reponse is a plain text string.

.. code-block::
    :caption: Example Request

    https://api.helioviewer.org/v2/getJP2Image/?date=2014-01-01T23:59:59Z&sourceId=14&jpip=true

.. code-block::
    :caption: Example Response

    jpip://api.helioviewer.org:8090/AIA/2014/01/02/335/2014_01_02__00_00_02_62__SDO_AIA_AIA_335.jp2


Example: JP2 Image (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

If request parameters `jpip` and `json` are both set to `true`, the reponse is
a JSON object.

.. code-block::
    :caption: Example Request

    https://api.helioviewer.org/v2/getJP2Image/?date=2014-01-01T23:59:59Z&sourceId=14&jpip=true&json=true

.. code-block::
    :caption: Example Response

    {
       "uri": "jpip://api.helioviewer.org:8090/AIA/2014/01/02/335/2014_01_02__00_00_02_62__SDO_AIA_AIA_335.jp2"
    }

.. table:: Response Description

    +-----------+-------------+--------+----------------------------------+
    | Parameter | Required    | Type   | Description                      |
    +===========+=============+========+==================================+
    | uri       | Required    | string | JPIP protocol link to JP2 image. |
    +-----------+-------------+--------+----------------------------------+
