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
    | sources   | Optional | string | HEK,CCMC             | | Specify the external data sources to use for the request |
    |           |          |        |                      | | If not provided, all sources will be queried             |
    |           |          |        |                      | | Allowed values: HEK, CCMC, RHESSI.                       |
    +-----------+----------+--------+----------------------+------------------------------------------------------------+
    | format    | Optional | string | | tree               | | Output shape. ``tree`` (default) returns the legacy      |
    |           |          |        | | simpletree         | | nested category/group structure described in             |
    |           |          |        | | flat               | | :ref:`helioviewer-event-format`. ``simpletree`` buckets  |
    |           |          |        |                      | | the flat response into a one-level ``SOURCE>>Label`` map |
    |           |          |        |                      | | keyed by event type. ``flat`` returns the new v1         |
    |           |          |        |                      | | per-source response (one object per event with no        |
    |           |          |        |                      | | category nesting). Allowed values: ``tree``,             |
    |           |          |        |                      | | ``simpletree``, ``flat``.                                |
    +-----------+----------+--------+----------------------+------------------------------------------------------------+

See :ref:`helioviewer-event-format` for the response format when ``format=tree``
(the default). When ``format=flat`` is requested, the response is the raw v1
events payload for each source, concatenated -- no nesting, no group keys.
When ``format=simpletree`` is requested, that flat payload is re-shaped into
a single-level map whose keys are ``SOURCE>>Label`` strings (one per known
event type for the requested sources) and whose values are arrays of the
matching events. Empty buckets are kept so the client can render the full
catalogue regardless of what's in the response.

Event specific data conforms to the `HEK Event Specification <https://www.lmsal.com/hek/VOEvent_Spec.html>`_

Example: Get HEK Events for 2023-03-30 (tree, default)

.. code-block::
    :caption: Example Query (format=tree)

    https://api.helioviewer.org/v2/events/?startTime=2023-03-30T00:00:00Z&sources=HEK

.. code-block::
    :caption: Example Response (format=tree)

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

Example: Same request but with the new flat shape

.. code-block::
    :caption: Example Query (format=flat)

    https://api.helioviewer.org/v2/events/?startTime=2023-03-30T00:00:00Z&sources=HEK&format=flat

.. code-block::
    :caption: Example Response (format=flat)

    [
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
            "concept": "Active Region",
            "frm_name": "NOAA SWPC Observer",
            "frm_institute": "NOAA-SWPC",
            "pin": "AR",
            ...
        },
        ...
    ]

Example: Same request bucketed into a SOURCE>>Label map

.. code-block::
    :caption: Example Query (format=simpletree)

    https://api.helioviewer.org/v2/events/?startTime=2023-03-30T00:00:00Z&sources=HEK&format=simpletree

.. code-block::
    :caption: Example Response (format=simpletree)

    {
        "HEK>>Active Region": [
            {
                "concept": "Active Region",
                "frm_name": "NOAA SWPC Observer",
                "path": "HEK>>Active Region>>NOAA SWPC Observer",
                "pin": "AR",
                ...
            },
            ...
        ],
        "HEK>>CME": [],
        "HEK>>Coronal Hole": [
            {
                "concept": "Coronal Hole",
                "frm_name": "SPoCA",
                "path": "HEK>>Coronal Hole>>SPoCA",
                ...
            }
        ],
        "HEK>>Flare": [],
        "HEK>>Sunspot": [],
        ...
    }

Every key in the response object is a ``SOURCE>>Label`` string covering the
event-type catalogue for the requested sources. Empty buckets are kept so
the client can know the full catalogue regardless of what's in the response.