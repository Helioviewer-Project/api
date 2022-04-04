downloadScreenshot
^^^^^^^^^^^^^^^^^^
GET /v2/downloadScreenshot/

Download a custom screenshot (that was generated using the `takeScreenshot` API
endpoint).

.. table:: Request Parameters:

    +-----------+----------+--------+---------+----------------------------------------------------------------------------------------+
    | Parameter | Required |  Type  | Example |                                      Description                                       |
    +===========+==========+========+=========+========================================================================================+
    |    id     | Required | number | 3240748 | Unique screenshot identifier (provided by the response to a `takeScreenshot` request). |
    +-----------+----------+--------+---------+----------------------------------------------------------------------------------------+

Example: binary (PNG image data)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/downloadScreenshot/?id=3240748
