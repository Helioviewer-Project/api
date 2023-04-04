getFlarePredictions
^^^^^^^^^^^^^^^^^^^
GET /v2/getFlarePredictions

Returns a list of the most relevant flare positions for the given observation time.
The predictions chosen are selected by being as close to the given date as possible, but not exceeding it.
Predictions are provided by the `Community Coordinated Modeling Center (CCMC) <https://ccmc.gsfc.nasa.gov/scoreboards/flare/>`_
The format of the returned data is in Helioviewer Event Format.

.. table:: `Request Parameters`

    +-----------+----------+--------+----------------------+---------------------------------------+
    | Parameter | Required | Type   | Example              | Description                           |
    +===========+==========+========+======================+=======================================+
    | startTime | Required | string | 2023-01-01T00:00:00Z | Specific time to get predictions for. |
    +-----------+----------+--------+----------------------+---------------------------------------+

See :ref:`helioviewer-event-format` for the response format.

.. table:: Event Specific Data

    +--------------+-------------+--------+----------------------------------------------------+
    | Parameter    | Required    | Type   | Description                                        |
    +==============+=============+========+====================================================+
    | start_window | Required    | date   | Beginning of the prediction window                 |
    +--------------+-------------+--------+----------------------------------------------------+
    | end_window   | Required    | date   | End of the prediction window                       |
    +--------------+-------------+--------+----------------------------------------------------+
    | issue_time   | Required    | date   | Time the prediction was issued                     |
    +--------------+-------------+--------+----------------------------------------------------+
    | c            | Optional    | string | Probability of a C-Class Flare                     |
    +--------------+-------------+--------+----------------------------------------------------+
    | m            | Optional    | string | Probability of a M-Class Flare                     |
    +--------------+-------------+--------+----------------------------------------------------+
    | x            | Optional    | string | Probability of a X-Class Flare                     |
    +--------------+-------------+--------+----------------------------------------------------+
    | cplus        | Optional    | string | Probability of a C-Class or larger flare           |
    +--------------+-------------+--------+----------------------------------------------------+
    | mplus        | Optional    | string | Probability of a M-Class or larger flare           |
    +--------------+-------------+--------+----------------------------------------------------+
    | latitude     | Required    | string | Latitude on sun associated with this prediction    |
    +--------------+-------------+--------+----------------------------------------------------+
    | longitude    | Required    | string | Longitude on sun associated with this prediction   |
    +--------------+-------------+--------+----------------------------------------------------+
    | hpc_x        | Required    | string | Helioprojective X coordinate of this prediction    |
    +--------------+-------------+--------+----------------------------------------------------+
    | hpc_y        | Required    | string | Helioprojective Y coordinate of this prediction    |
    +--------------+-------------+--------+----------------------------------------------------+
    | dataset      | Required    | string | CCMC Flare Scoreboard dataset for this prediction  |
    +--------------+-------------+--------+----------------------------------------------------+


Example: Get predictions for 2023-03-30 15:00:00 UTC
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Query

    https://api.helioviewer.org/v2/getFlarePredictions/?startTime=2023-03-30T15:00:00Z

.. code-block::
    :caption: Example Response

    [
        {
            "name": "Solar Flare Predictions",
            "pin": "FP",
            "groups": [
            {
                "name": "ASSA_1_REGIONS",
                "contact": "",
                "url": "https://ccmc.gsfc.nasa.gov/scoreboards/flare/",
                "data": [
                {
                    "id": "270",
                    "start_window": "2023-03-30 12:00:00",
                    "end_window": "2023-03-31 00:00:00",
                    "issue_time": "2023-03-30 12:00:00",
                    "c": "0.13",
                    "m": "0.04",
                    "x": "0",
                    "cplus": null,
                    "mplus": null,
                    "latitude": "69",
                    "longitude": "18",
                    "hpc_x": "106.494",
                    "hpc_y": "929.679",
                    "dataset": "ASSA_1_REGIONS",
                    "hv_hpc_x": 113.72144977234295,
                    "hv_hpc_y": 929.3633651907943,
                    "label": "ASSA_1_REGIONS",
                    "version": "ASSA_1_REGIONS",
                    "type": "FP",
                    "start": "2023-03-30 12:00:00",
                    "end": "2023-03-31 00:00:00"
                },
                ...
                ]
            },
            ...
            ]
        }
    ]