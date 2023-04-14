Events
^^^^^^
GET /v2/events

Returns a list of HEK events in the :ref:`helioviewer-event-format`.

.. table:: `Request Parameters`

    +-----------+----------+--------+----------------------+------------------------------------------------------------+
    | Parameter | Required | Type   | Example              | Description                                                |
    +===========+==========+========+======================+============================================================+
    | startTime | Required | string | 2023-01-01T00:00:00Z | Specific time to get predictions for.                      |
    +-----------+----------+--------+----------------------+------------------------------------------------------------+
    | sources   | Optional | string | HEK,DONKI            | | Specify the external data sources to use for the request |
    |           |          |        |                      | | If not provided, all sources will be queried             |
    |           |          |        |                      | | Current options are HEK and DONKI.                       |
    +-----------+----------+--------+----------------------+------------------------------------------------------------+

See :ref:`helioviewer-event-format` for the response format.

Event specific data conforms to the `HEK Event Specification <https://www.lmsal.com/hek/VOEvent_Spec.html>`_

Example: Get HEK Events for 2023-03-30

.. code-block::
    :caption: Example Query

    https://api.helioviewer.org/v2/events/?date=2023-03-30T00:00:00Z

.. code-block::
    :caption: Example Response

    [
        {
            "name": "Active Region",
            "pin": "AR",
            "groups": [
            {
                "name": "NOAA SWPC Observer",
                "contact": "http://www.swpc.noaa.gov/",
                "url": "N/A",
                "data": [
                {
                    "absnetcurrenthelicity": null,
                    "active": "true",
                    "area_atdiskcenter": 213057280,
                    "area_atdiskcenteruncert": null,
                    "area_raw": null,
                    "area_uncert": null,
                    "area_unit": "km2",
                    "ar_axislength": null,
                    "ar_compactnesscls": "",
                    "ar_lengthunit": "",
                    "ar_mcintoshcls": "HAX",
                    "ar_mtwilsoncls": "ALPHA",
                    "ar_neutrallength": null,
                    ...
                },
                ...
                ]
            },
            ...
            ]
        },
        ...
    ]