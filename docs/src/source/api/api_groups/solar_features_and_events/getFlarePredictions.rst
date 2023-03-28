getFlarePredictions
^^^^^^^^^^^^^^^^^^^
GET /v2/getFlarePredictions

Returns a list of the most relevant flare positions for the given observation time.
The predictions chosen are selected by being as close to the given date as possible, but not exceeding it.
Predictions are provided by the `Community Coordinated Modeling Center (CCMC) <https://ccmc.gsfc.nasa.gov/scoreboards/flare/>`_

.. table:: `Request Parameters`

    +-----------+----------+--------+----------------------+---------------------------------------+
    | Parameter | Required | Type   | Example              | Description                           |
    +===========+==========+========+======================+=======================================+
    | date      | Required | string | 2023-01-01T00:00:00Z | Specific time to get predictions for. |
    +-----------+----------+--------+----------------------+---------------------------------------+

.. table:: Response Description

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


Example: Get predictions for 2023-03-01 15:00:00 UTC
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

.. code-block::
    :caption: Example Query

    https://api.helioviewer.org/v2/getFlarePredictions/?date=2023-03-01T15:00:00Z

.. code-block::
    :caption: Example Response

    [
        {
            "start_window": "2023-03-01 12:30:00",
            "end_window": "2023-03-02 12:30:00",
            "issue_time": "2023-03-01 12:30:26",
            "c": null,
            "m": null,
            "x": "0.01",
            "cplus": "0.05",
            "mplus": "0.01",
            "latitude": "-23",
            "longitude": "58",
            "hpc_x": "757.69",
            "hpc_y": "-316.737",
            "dataset": "SIDC_Operator_REGIONS"
        },
        {
            "start_window": "2023-03-01 12:30:00",
            "end_window": "2023-03-02 12:30:00",
            "issue_time": "2023-03-01 12:30:26",
            "c": null,
            "m": null,
            "x": "0.1",
            "cplus": "0.95",
            "mplus": "0.4",
            "latitude": "25",
            "longitude": "36",
            "hpc_x": "517.403",
            "hpc_y": "496.716",
            "dataset": "SIDC_Operator_REGIONS"
        },
        ...
    ]