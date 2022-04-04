checkYouTubeAuth
^^^^^^^^^^^^^^^^
GET /v2/checkYouTubeAuth/

Check to see if Helioveiwer.org is authorized to interact with a user's YouTube
account via the current browser session.

.. table:: Request Parameters:

    +-----------+----------+--------+---------------------------------------------------------------+-------------+
    | Parameter | Required |  Type  |                            Example                            | Description |
    +===========+==========+========+===============================================================+=============+
    | callback  | Optional | string | Wrap the response object in a function call of your choosing. |             |
    +-----------+----------+--------+---------------------------------------------------------------+-------------+

Example: string (Boolean)
~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/checkYouTubeAuth/?

.. code-block::
    :caption: Example Response:

    false
