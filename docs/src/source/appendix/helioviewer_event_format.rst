.. _helioviewer-event-format:

Heliovewer Event Format
=======================

.. warning::
    This format is undergoing active development and may change without notice.

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
    | link     | Optional Link Object containing link text and URL                         |
    +----------+---------------------------------------------------------------------------+
    | source   | Event specific data                                                       |
    +----------+---------------------------------------------------------------------------+
    | views    | | List of views into the data.                                            |
    |          | | This is parsed into the tabs that appear on Helioviewer                 |
    +----------+---------------------------------------------------------------------------+
    | title    | Event information dialog popup title                                      |
    +----------+---------------------------------------------------------------------------+

The event object allows normalized positioning and labeling for any type of event while still containing the event-specific data.
Helioviewer is using this format for all features and events.
Any new datasource can be converted into this format for use on Helioviewer.

External Links
--------------
Very often events may have some information hosted by the data provider.
For example, CME Analyses provided by the Space Weather Database Of Notifications, Knowledege, Information (DONKI) will have links back to DONKI's website.
Thinks links can be integrated into Helioviewer by specifying a link with url and text.
This link will appear in the popup that shows up when an event is clicked.

.. table:: `Link Object`

    +----------+---------------------------------------------------------------------------+
    | Field    | Description                                                               |
    +==========+===========================================================================+
    | url      | Link URL                                                                  |
    +----------+---------------------------------------------------------------------------+
    | text     | Text to show on Helioviewer for the link                                  |
    +----------+---------------------------------------------------------------------------+

Views
-----
Sometimes the raw data returned by the API can contain far too much information to display in one tab.
It's also given in some machine readable format, which is not necessarily the best way for humans to view the data.
By using views, we can programmatically generate specialized tabs for looking at particular pieces of the source data.
A view contains a tab name, a tab group, and the tab content.

.. table:: `View`

    +----------+-----------------------------------------------------------------------------------------+
    | Field    | Description                                                                             |
    +==========+=========================================================================================+
    | name     | This view's title. It should describe the content in a short title.                     |
    +----------+-----------------------------------------------------------------------------------------+
    | content  | Object of key - value pairs which define the content. It should not have nested objects |
    +----------+-----------------------------------------------------------------------------------------+
    | tabgroup | | Optional field. A number specifying an association between multiple views.            |
    |          | | This can be used to indicate multiple views are related in some way.                  |
    |          | | The Helioviewer client will visually place these tabs next to each other              |
    +----------+-----------------------------------------------------------------------------------------+

Contributing Data to Helioviewer
--------------------------------

If you would like your data to appear on Helioviewer, please see `Helioviewer Event Interface <https://github.com/dgarciabriseno/helioviewer-event-interface/>`_.