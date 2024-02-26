getEclipseImage
^^^^^^^^^^^^^^^
GET /v2/getEclipseImage/

Generates a grayscale image of LASCO C2 with a preset FOV and optional moon.

.. table:: Request Parameters:

    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+
    |  Parameter  | Required |  Type   |                        Example                         |                                                                                 Description                                                                                 |
    +=============+==========+=========+========================================================+=============================================================================================================================================================================+
    | moon        | Optional | boolean | "true" or "false"                                      | Show a moon in the place where the sun would be                                                                                                                             |
    +-------------+----------+---------+--------------------------------------------------------+-----------------------------------------------------------------------------------------------------------------------------------------------------------------------------+


Example:
~~~~~~~~

JSON response to "takeScreenshot" API requests. Assumes that the `display`
parameter was omitted or set to `false`.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getEclipseImage/

.. code-block::
    :caption: Example Response:

    Response is an image file.
