.. _helioviewer-event-format:

Heliovewer Event Format
=======================

The Helioviewer Event Format is data encapsulation format that makes it easier for clients (specifically Helioviewer) to parse event data.

The format consists of an Array of objects which contain metadata and the data itself.

Here is a sample of the Solar Flare Prediction data looks like in the Helioviewer Event Format:

.. code-block::
    :caption: Helioviewer Event Format

    [
        {
            "name": "Solar Flare Predictions",
            "pin": "FP",
            "groups": [{
                "name": "ASSA_1_REGIONS",
                "contact": "",
                "url": "https://ccmc.gsfc.nasa.gov/scoreboards/flare/",
                "data": [{
                    "id": "270",
                    "hv_hpc_x": 116.63562039275276,
                    "hv_hpc_y": 929.2300914486825,
                    "label": "ASSA_1_REGIONS",
                    "version": "ASSA_1_REGIONS",
                    "type": "FP",
                    "start": "2023-03-30 12:00:00",
                    "end": "2023-03-31 00:00:00",
                    ... Event specific data ...
                }],
            },
            ... Other groups ...
            ],
        },
        ... Other types of events ...
    ]

Let's break down the above format. The Helioviewer Event Format is an array of Type objects.
The Type object must contain the following fields:

.. table:: `Type Object`

    +--------+--------------------------------------------------------------------------------------------------------------+
    | Field  | Description                                                                                                  |
    +========+==============================================================================================================+
    | name   | The name of the overall group of data                                                                        |
    +--------+--------------------------------------------------------------------------------------------------------------+
    | pin    | The pin to use when rendering the item. In Helioviewer this is an abbreviation. Other clients may ignore it. |
    +--------+--------------------------------------------------------------------------------------------------------------+
    | groups | Array of Group Objecs                                                                                        |
    +--------+--------------------------------------------------------------------------------------------------------------+

On Helioviewer, the name of the type object is associated with the checkboxes under the Features and Events section.
Within each Type object is a list of Group Objects:

.. table:: `Group Object`

    +---------+------------------------------------------------------------------------------------------------------------------------+
    | Field   | Description                                                                                                            |
    +=========+========================================================================================================================+
    | name    | Title for the group. For HEK this is the feature recognition method, for Flare Predictions it is the prediction method |
    +---------+------------------------------------------------------------------------------------------------------------------------+
    | contact | Contact information about this data source. The field must be present, but may be empty.                               |
    +---------+------------------------------------------------------------------------------------------------------------------------+
    | url     | Url referring to the source of this data group.                                                                        |
    +---------+------------------------------------------------------------------------------------------------------------------------+
    | data    | Array of Event Objects which contain event specific data.                                                              |
    +---------+------------------------------------------------------------------------------------------------------------------------+

The groups represent subsections of the overall Event type.
This is modelled after the HEK data where a feature such as Active Regions can be identified using various feature recognition methods.
The data field is where the actual event specific data goes.

.. table:: `Event Object`

    +----------+---------------------------------------------------------------------------+
    | Field    | Description                                                               |
    +==========+===========================================================================+
    | id       | Unique ID for this event                                                  |
    +----------+---------------------------------------------------------------------------+
    | hv_hpc_x | Helioprojective X Coordinate for Helioviewer                              |
    +----------+---------------------------------------------------------------------------+
    | hv_hpc_y | Helioprojective Y Coordinate for Helioviewer                              |
    +----------+---------------------------------------------------------------------------+
    | label    | Label to display in the UI for this event.                                |
    +----------+---------------------------------------------------------------------------+
    | version  | Specific version of the method used to identify this event. May be empty. |
    +----------+---------------------------------------------------------------------------+
    | type     | Abbreviation representing the event type.                                 |
    +----------+---------------------------------------------------------------------------+
    | start    | UTC Start time for the event.                                             |
    +----------+---------------------------------------------------------------------------+
    | end      | UTC End time for the event.                                               |
    +----------+---------------------------------------------------------------------------+
    | ...      | Event specific data                                                       |
    +----------+---------------------------------------------------------------------------+

The event object allows normalized positioning and labeling for any type of event while still containing the event-specific data.
Helioviewer is using this format for all features and events.
Any new datasource can be converted into this format for use on Helioviewer.