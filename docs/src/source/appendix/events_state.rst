.. _events-state-page:

Events State
============

This document describes the structure of the events state JSON string, 
which is used for filtering included event markers into the screenshots and movies , also for controlling the visibility of their labels.

Structure
---------

The Event State data type is a JSON object with the following structure:

.. code-block:: json

    {
        "event_group_key": {
            "labels_visible": true,
            "layers": [
                {
                    "event_type": "flare",
                    "frms": ["frm10", "frm20"],
                    "event_instances": ["flare--frm1--event1", "flare--frm2--event2"]
                }
            ]
        }
    }

Parameters
----------

The following table describes each parameter in the JSON object:

.. list-table:: Event State Parameters
   :header-rows: 1

   * - Parameter
     - Type
     - Description
   * - ``event_group_key``
     - object
     - The root object representing an event layer grouping, such as CCMC or HEK. This contains the configuration for the grouping.
   * - ``event_group_key.labels_visible``
     - boolean
     - Controls the visibility of all event labels under this event layer grouping.
   * - ``event_group_key.layers``
     - array
     - An array of layer objects specifying which events to include in the generated screenshots and movies.

Each layer object within the ``layers`` array contains the following fields:

.. list-table:: Layer Parameters
   :header-rows: 1

   * - Parameter
     - Type
     - Description
   * - ``event_type``
     - string
     - The pin of the event (e.g., "FP") to be included into the screenshot and movies. Please see :ref:`helioviewer-event-format` for getting more information about event pin 
   * - ``frms``
     - array
     - An array of strings representing for the event group names to be included into the screenshot and movies. Please see :ref:`helioviewer-event-format` for getting more information about event group title 
   * - ``event_instances``
     - array
     - An array of strings representing the unique IDs of the event instances. Please see :ref:`event-instance-algorithm` for details on generating these IDs from events.

Example
-------

Below is an example of a Event State JSON object:

.. code-block:: json

    {
        "tree_HEK": {
            "labels_visible": true,
            "layers": [
                {
                    "event_type": "flare",
                    "frms": ["frm10", "frm20"],
                    "event_instances": ["flare--frm1--event1", "flare--frm2--event2"]
                }
            ]
        }
    }

Description
-----------

- **labels_visible**: This boolean field indicates whether the labels for all the event labels under this HEK tree configuration should be visible. If set to `true`, labels are visible; if set to `false`, labels are hidden.
- **layers**: This array contains filtering configuration specifying which events should be included in to the generated screenshots and movies. Each layer provides different levels of filtering:

  - **event_type**: Includes all the events under this event pin.
  - **frms**: Includes all of the events associated with the group names in this array. Please see :ref:`helioviewer-event-format` for getting more information about event group names.
  - **event_instances**: Includes specific event instances identified by their unique IDs. Each ID follows the format `event_type--frm--event_id`. Please see :ref:`event-instance-algorithm` for details on generating these IDs.

This structure allows you to filter which event markers are included in the generated screenshots and movies.

.. _event-instance-algorithm:

Individual Event IDs
--------------------

.. warning::
    This event ID generation is undergoing active development and may change without notice.

Event IDs are generated from three components:

- **event_pin**: The pin of the event.
- **event_group_name**: The name of the event group.
- **event_id**: The unique identifier of the event.

Please see :ref:`helioviewer-event-format` for more information about these fields. After obtaining these fields, users should base64 encode ``event_id``, perform some cleaning, and join them with ``--``.

Here is our implementation in PHP.

.. code-block:: php

    <?php
        $event_id_pieces = [
            $event_pin,
            $event_group_name,
            base64_encode($event['id']),
        ];
        $cleaned_event_id_pieces = array_map(function($p) {
            return str_replace([' ','=','+','.','(',')'], ['_','_','\+','\.','\(','\)'], $p);
        }, $event_id_pieces);
        return join('--', $cleaned_event_id_pieces);
    ?>


This method ensures that the event IDs are unique and suitable for use in filtering events.


