getDataSources
^^^^^^^^^^^^^^^
GET /v2/getDataSources/

Return a hierarchial list of the available datasources.

Optional parameter `verbose` is exists for compatability with JHelioviewer. It
outputs the hierarchical list in an alternative format and limits the list of
available datasources to a known set (SDO and SOHO). JHelioviewer may not
operate properly if new datasources appear in the feed without a client-side
updgrade. To explicitly include new sources, use the optional `enable` parameter.

.. table:: Request Parameters:

    +-----------+----------+---------+----------------------------+---------------------------------------------------------------+
    | Parameter | Required |  Type   |          Example           |                          Description                          |
    +===========+==========+=========+============================+===============================================================+
    |  verbose  | Optional | boolean |           false            |                                                               |
    +-----------+----------+---------+----------------------------+---------------------------------------------------------------+
    |  enable   | Optional | string  | [Yohkoh,STEREO_A,STEREO_B] |       Comma-separated list of observatories to enable.        |
    +-----------+----------+---------+----------------------------+---------------------------------------------------------------+
    | callback  | Optional | string  |                            | Wrap the response object in a function call of your choosing. |
    +-----------+----------+---------+----------------------------+---------------------------------------------------------------+

Example: Get Data Sources (JSON)

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getDataSources/?

.. code-block::
    :caption: Example Response:

    {
      "Hinode": {
        "XRT": {
          "Al_med": {
            "Al_mesh": {
              "end": "2007-05-09 09:50:35",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Al_mesh",
              "sourceId": 38,
              "start": "2006-11-02 10:25:55",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2013-04-12 22:30:11",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Al_thick",
              "sourceId": 39,
              "start": "2006-11-02 10:29:19",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2013-10-10 06:13:06",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Be_thick",
              "sourceId": 40,
              "start": "2006-11-02 10:30:27",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2007-04-12 09:21:51",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Gband",
              "sourceId": 41,
              "start": "2006-10-27 03:14:51",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2013-10-16 15:14:57",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Open",
              "sourceId": 42,
              "start": "2006-11-02 10:25:05",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2010-11-18 14:09:50",
              "layeringOrder": 1,
              "nickname": "XRT Al_med/Ti_poly",
              "sourceId": 43,
              "start": "2006-11-02 10:27:03",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          },
          "Al_poly": {
            "Al_mesh": {
              "end": "2007-12-23 10:04:47",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Al_mesh",
              "sourceId": 44,
              "start": "2006-11-02 10:20:35",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2013-04-30 02:19:57",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Al_thick",
              "sourceId": 45,
              "start": "2006-11-02 10:21:13",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2013-04-12 22:29:33",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Be_thick",
              "sourceId": 46,
              "start": "2006-11-02 10:21:17",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2007-04-20 09:25:05",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Gband",
              "sourceId": 47,
              "start": "2006-10-27 02:16:32",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2013-10-22 08:28:16",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Open",
              "sourceId": 48,
              "start": "2006-10-23 10:37:13",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2013-10-19 14:59:37",
              "layeringOrder": 1,
              "nickname": "XRT Al_poly/Ti_poly",
              "sourceId": 49,
              "start": "2006-11-02 10:21:04",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Al_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          },
          "Be_med": {
            "Al_mesh": {
              "end": "2007-05-09 09:50:19",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Al_mesh",
              "sourceId": 50,
              "start": "2006-11-02 10:23:14",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2006-11-02 10:24:02",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Al_thick",
              "sourceId": 51,
              "start": "2006-11-02 10:24:02",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2006-11-02 10:24:28",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Be_thick",
              "sourceId": 52,
              "start": "2006-11-02 10:24:28",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2007-04-20 07:51:35",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Gband",
              "sourceId": 53,
              "start": "2006-10-27 03:03:11",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2013-10-21 17:14:06",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Open",
              "sourceId": 54,
              "start": "2006-11-02 10:23:05",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2006-11-02 10:23:26",
              "layeringOrder": 1,
              "nickname": "XRT Be_med/Ti_poly",
              "sourceId": 55,
              "start": "2006-11-02 10:23:26",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_med"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          },
          "Be_thin": {
            "Al_mesh": {
              "end": "2007-05-09 09:50:03",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Al_mesh",
              "sourceId": 56,
              "start": "2006-11-02 10:22:19",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2006-11-02 10:22:35",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Al_thick",
              "sourceId": 57,
              "start": "2006-11-02 10:22:35",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2006-11-02 10:22:42",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Be_thick",
              "sourceId": 58,
              "start": "2006-11-02 10:22:42",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2007-04-20 03:13:11",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Gband",
              "sourceId": 59,
              "start": "2006-10-27 02:51:31",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2013-10-22 08:28:01",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Open",
              "sourceId": 60,
              "start": "2006-11-02 10:22:14",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2006-11-02 10:22:24",
              "layeringOrder": 1,
              "nickname": "XRT Be_thin/Ti_poly",
              "sourceId": 61,
              "start": "2006-11-02 10:22:24",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Be_thin"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          },
          "C_poly": {
            "Al_mesh": {
              "end": "2007-05-09 09:49:47",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Al_mesh",
              "sourceId": 62,
              "start": "2006-11-02 10:21:26",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2013-04-12 22:29:49",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Al_thick",
              "sourceId": 63,
              "start": "2006-11-02 10:22:04",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2006-11-02 10:22:08",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Be_thick",
              "sourceId": 64,
              "start": "2006-11-02 10:22:08",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2006-11-02 10:21:34",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Gband",
              "sourceId": 65,
              "start": "2006-10-27 02:39:52",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2013-10-19 14:59:17",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Open",
              "sourceId": 66,
              "start": "2006-11-02 10:21:22",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2012-11-03 19:30:16",
              "layeringOrder": 1,
              "nickname": "XRT C_poly/Ti_poly",
              "sourceId": 67,
              "start": "2006-11-02 10:21:30",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "C_poly"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          },
          "Mispositioned": {
            "Mispositioned": {
              "end": "2006-12-07 09:13:47",
              "layeringOrder": 1,
              "nickname": "XRT Mispositioned/Mispositioned",
              "sourceId": 68,
              "start": "2006-12-02 09:22:05",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Mispositioned"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Mispositioned"
                }
              ]
            }
          },
          "Open": {
            "Al_mesh": {
              "end": "2013-10-22 06:33:47",
              "layeringOrder": 1,
              "nickname": "XRT Open/Al_mesh",
              "sourceId": 69,
              "start": "2006-10-26 22:55:51",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_mesh"
                }
              ]
            },
            "Al_thick": {
              "end": "2013-10-22 08:03:58",
              "layeringOrder": 1,
              "nickname": "XRT Open/Al_thick",
              "sourceId": 70,
              "start": "2006-10-27 04:10:52",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Al_thick"
                }
              ]
            },
            "Be_thick": {
              "end": "2013-10-22 00:25:13",
              "layeringOrder": 1,
              "nickname": "XRT Open/Be_thick",
              "sourceId": 71,
              "start": "2006-10-27 04:22:32",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Be_thick"
                }
              ]
            },
            "Gband": {
              "end": "2013-10-22 08:00:58",
              "layeringOrder": 1,
              "nickname": "XRT Open/Gband",
              "sourceId": 72,
              "start": "2006-10-24 09:35:12",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Gband"
                }
              ]
            },
            "Open": {
              "end": "2012-06-19 11:52:20",
              "layeringOrder": 1,
              "nickname": "XRT Open/Open",
              "sourceId": 73,
              "start": "2006-12-05 08:04:05",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Open"
                }
              ]
            },
            "Ti_poly": {
              "end": "2013-10-22 08:28:11",
              "layeringOrder": 1,
              "nickname": "XRT Open/Ti_poly",
              "sourceId": 74,
              "start": "2006-10-26 22:56:37",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "Hinode"
                },
                {
                  "label": "Instrument",
                  "name": "XRT"
                },
                {
                  "label": "Filter Wheel 1",
                  "name": "Open"
                },
                {
                  "label": "Filter Wheel 2",
                  "name": "Ti_poly"
                }
              ]
            }
          }
        }
      },
      "PROBA2": {
        "SWAP": {
          "174": {
            "end": "2013-12-05 10:56:16",
            "layeringOrder": 1,
            "nickname": "SWAP 174",
            "sourceId": 32,
            "start": "2010-01-04 17:00:50",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "PROBA2"
              },
              {
                "label": "Instrument",
                "name": "SWAP"
              },
              {
                "label": "Measurement",
                "name": "174"
              }
            ]
          }
        }
      },
      "SDO": {
        "AIA": {
          "131": {
            "end": "2013-12-05 13:43:44",
            "layeringOrder": 1,
            "nickname": "AIA 131",
            "sourceId": 9,
            "start": "2010-06-02 00:05:34",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "131"
              }
            ]
          },
          "1600": {
            "end": "2013-12-05 13:49:28",
            "layeringOrder": 1,
            "nickname": "AIA 1600",
            "sourceId": 15,
            "start": "2010-06-02 00:05:30",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "1600"
              }
            ]
          },
          "1700": {
            "end": "2013-12-05 13:50:30",
            "layeringOrder": 1,
            "nickname": "AIA 1700",
            "sourceId": 16,
            "start": "2010-06-23 00:00:31",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "1700"
              }
            ]
          },
          "171": {
            "end": "2013-12-05 13:44:47",
            "layeringOrder": 1,
            "nickname": "AIA 171",
            "sourceId": 10,
            "start": "2010-06-02 00:05:36",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "171"
              }
            ]
          },
          "193": {
            "end": "2013-12-05 13:45:42",
            "layeringOrder": 1,
            "nickname": "AIA 193",
            "sourceId": 11,
            "start": "2010-06-02 00:05:31",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "193"
              }
            ]
          },
          "211": {
            "end": "2013-12-05 13:46:35",
            "layeringOrder": 1,
            "nickname": "AIA 211",
            "sourceId": 12,
            "start": "2010-06-02 00:05:37",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "211"
              }
            ]
          },
          "304": {
            "end": "2013-12-05 13:48:43",
            "layeringOrder": 1,
            "nickname": "AIA 304",
            "sourceId": 13,
            "start": "2010-06-02 00:05:39",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "304"
              }
            ]
          },
          "335": {
            "end": "2013-12-05 13:49:38",
            "layeringOrder": 1,
            "nickname": "AIA 335",
            "sourceId": 14,
            "start": "2010-06-02 00:05:28",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "335"
              }
            ]
          },
          "4500": {
            "end": "2013-12-05 13:00:07",
            "layeringOrder": 1,
            "nickname": "AIA 4500",
            "sourceId": 17,
            "start": "2010-06-02 00:05:44",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "4500"
              }
            ]
          },
          "94": {
            "end": "2013-12-05 13:43:01",
            "layeringOrder": 1,
            "nickname": "AIA 94",
            "sourceId": 8,
            "start": "2010-06-02 00:05:33",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "AIA"
              },
              {
                "label": "Measurement",
                "name": "94"
              }
            ]
          }
        },
        "HMI": {
          "continuum": {
            "end": "2013-12-05 11:20:40",
            "layeringOrder": 1,
            "nickname": "HMI Int",
            "sourceId": 18,
            "start": "2010-12-06 06:53:41",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "HMI"
              },
              {
                "label": "Measurement",
                "name": "continuum"
              }
            ]
          },
          "magnetogram": {
            "end": "2013-12-05 12:18:25",
            "layeringOrder": 1,
            "nickname": "HMI Mag",
            "sourceId": 19,
            "start": "2010-12-06 06:53:41",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SDO"
              },
              {
                "label": "Instrument",
                "name": "HMI"
              },
              {
                "label": "Measurement",
                "name": "magnetogram"
              }
            ]
          }
        }
      },
      "SOHO": {
        "EIT": {
          "171": {
            "end": "2013-08-07 13:00:13",
            "layeringOrder": 1,
            "nickname": "EIT 171",
            "sourceId": 0,
            "start": "1996-01-15 21:39:21",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "EIT"
              },
              {
                "label": "Measurement",
                "name": "171"
              }
            ]
          },
          "195": {
            "end": "2013-08-07 01:13:50",
            "layeringOrder": 1,
            "nickname": "EIT 195",
            "sourceId": 1,
            "start": "1996-01-15 20:51:47",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "EIT"
              },
              {
                "label": "Measurement",
                "name": "195"
              }
            ]
          },
          "284": {
            "end": "2013-08-07 13:06:09",
            "layeringOrder": 1,
            "nickname": "EIT 284",
            "sourceId": 2,
            "start": "1996-01-15 21:04:17",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "EIT"
              },
              {
                "label": "Measurement",
                "name": "284"
              }
            ]
          },
          "304": {
            "end": "2013-08-07 01:19:42",
            "layeringOrder": 1,
            "nickname": "EIT 304",
            "sourceId": 3,
            "start": "1996-01-15 22:00:17",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "EIT"
              },
              {
                "label": "Measurement",
                "name": "304"
              }
            ]
          }
        },
        "LASCO": {
          "C2": {
            "white-light": {
              "end": "2013-12-05 07:12:05",
              "layeringOrder": 2,
              "nickname": "LASCO C2",
              "sourceId": 4,
              "start": "1996-04-01 01:12:15",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "SOHO"
                },
                {
                  "label": "Instrument",
                  "name": "LASCO"
                },
                {
                  "label": "Detector",
                  "name": "C2"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          },
          "C3": {
            "white-light": {
              "end": "2013-12-05 07:18:05",
              "layeringOrder": 3,
              "nickname": "LASCO C3",
              "sourceId": 5,
              "start": "1996-04-14 09:48:18",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "SOHO"
                },
                {
                  "label": "Instrument",
                  "name": "LASCO"
                },
                {
                  "label": "Detector",
                  "name": "C3"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          }
        },
        "MDI": {
          "continuum": {
            "end": "2011-01-11 22:39:00",
            "layeringOrder": 1,
            "nickname": "MDI Int",
            "sourceId": 7,
            "start": "1996-05-19 19:08:35",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "MDI"
              },
              {
                "label": "Measurement",
                "name": "continuum"
              }
            ]
          },
          "magnetogram": {
            "end": "2011-01-11 22:39:00",
            "layeringOrder": 1,
            "nickname": "MDI Mag",
            "sourceId": 6,
            "start": "1996-04-21 00:30:04",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "SOHO"
              },
              {
                "label": "Instrument",
                "name": "MDI"
              },
              {
                "label": "Measurement",
                "name": "magnetogram"
              }
            ]
          }
        }
      },
      "STEREO_A": {
        "SECCHI": {
          "COR1": {
            "white-light": {
              "end": "2013-12-01 09:50:00",
              "layeringOrder": 2,
              "nickname": "COR1-A",
              "sourceId": 28,
              "start": "2010-01-01 00:05:00",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "COR1"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          },
          "COR2": {
            "white-light": {
              "end": "2013-11-30 23:54:00",
              "layeringOrder": 3,
              "nickname": "COR2-A",
              "sourceId": 29,
              "start": "2010-01-01 00:24:00",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "COR2"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          },
          "EUVI": {
            "171": {
              "end": "2013-11-30 22:14:00",
              "layeringOrder": 1,
              "nickname": "EUVI-A 171",
              "sourceId": 20,
              "start": "2010-01-01 00:14:00",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "171"
                }
              ]
            },
            "195": {
              "end": "2013-11-30 23:55:30",
              "layeringOrder": 1,
              "nickname": "EUVI-A 195",
              "sourceId": 21,
              "start": "2010-01-01 00:05:30",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "195"
                }
              ]
            },
            "284": {
              "end": "2013-11-30 22:16:30",
              "layeringOrder": 1,
              "nickname": "EUVI-A 284",
              "sourceId": 22,
              "start": "2010-01-01 00:16:30",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "284"
                }
              ]
            },
            "304": {
              "end": "2013-11-30 23:56:15",
              "layeringOrder": 1,
              "nickname": "EUVI-A 304",
              "sourceId": 23,
              "start": "2010-01-01 00:06:15",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_A"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "304"
                }
              ]
            }
          }
        }
      },
      "STEREO_B": {
        "SECCHI": {
          "COR1": {
            "white-light": {
              "end": "2013-12-01 03:51:00",
              "layeringOrder": 2,
              "nickname": "COR1-B",
              "sourceId": 30,
              "start": "2010-01-01 00:05:37",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "COR1"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          },
          "COR2": {
            "white-light": {
              "end": "2013-11-30 23:55:00",
              "layeringOrder": 3,
              "nickname": "COR2-B",
              "sourceId": 31,
              "start": "2010-01-01 00:24:37",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "COR2"
                },
                {
                  "label": "Measurement",
                  "name": "white-light"
                }
              ]
            }
          },
          "EUVI": {
            "171": {
              "end": "2013-11-30 22:15:00",
              "layeringOrder": 1,
              "nickname": "EUVI-B 171",
              "sourceId": 24,
              "start": "2010-01-01 00:07:52",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "171"
                }
              ]
            },
            "195": {
              "end": "2013-11-30 23:56:30",
              "layeringOrder": 1,
              "nickname": "EUVI-B 195",
              "sourceId": 25,
              "start": "2010-01-01 00:06:07",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "195"
                }
              ]
            },
            "284": {
              "end": "2013-11-30 22:17:30",
              "layeringOrder": 1,
              "nickname": "EUVI-B 284",
              "sourceId": 26,
              "start": "2010-01-01 00:07:07",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "284"
                }
              ]
            },
            "304": {
              "end": "2013-11-30 23:57:15",
              "layeringOrder": 1,
              "nickname": "EUVI-B 304",
              "sourceId": 27,
              "start": "2010-01-01 00:06:52",
              "uiLabels": [
                {
                  "label": "Observatory",
                  "name": "STEREO_B"
                },
                {
                  "label": "Instrument",
                  "name": "SECCHI"
                },
                {
                  "label": "Detector",
                  "name": "EUVI"
                },
                {
                  "label": "Measurement",
                  "name": "304"
                }
              ]
            }
          }
        }
      },
      "TRACE": {
        "1216": {
          "end": "2008-10-03 00:20:37",
          "layeringOrder": 1,
          "nickname": "TRACE 1216",
          "sourceId": 78,
          "start": "2008-09-19 01:04:32",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "1216"
            }
          ]
        },
        "1550": {
          "end": "2008-10-03 00:20:19",
          "layeringOrder": 1,
          "nickname": "TRACE 1550",
          "sourceId": 79,
          "start": "2008-09-18 00:45:30",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "1550"
            }
          ]
        },
        "1600": {
          "end": "2008-10-06 22:08:53",
          "layeringOrder": 1,
          "nickname": "TRACE 1600",
          "sourceId": 80,
          "start": "2008-09-18 10:52:31",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "1600"
            }
          ]
        },
        "1700": {
          "end": "2008-10-03 00:20:26",
          "layeringOrder": 1,
          "nickname": "TRACE 1700",
          "sourceId": 81,
          "start": "2008-09-19 01:04:21",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "1700"
            }
          ]
        },
        "171": {
          "end": "2008-10-06 22:55:32",
          "layeringOrder": 1,
          "nickname": "TRACE 171",
          "sourceId": 75,
          "start": "2008-09-18 00:00:54",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "171"
            }
          ]
        },
        "195": {
          "end": "2008-10-06 06:59:56",
          "layeringOrder": 1,
          "nickname": "TRACE 195",
          "sourceId": 76,
          "start": "2008-09-18 00:38:24",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "195"
            }
          ]
        },
        "284": {
          "end": "2008-09-24 19:25:02",
          "layeringOrder": 1,
          "nickname": "TRACE 284",
          "sourceId": 77,
          "start": "2008-09-18 10:51:36",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "284"
            }
          ]
        },
        "white-light": {
          "end": "2008-10-06 22:54:05",
          "layeringOrder": 1,
          "nickname": "TRACE white-light",
          "sourceId": 82,
          "start": "2008-09-19 01:04:24",
          "uiLabels": [
            {
              "label": "Observatory",
              "name": "TRACE"
            },
            {
              "label": "Measurement",
              "name": "white-light"
            }
          ]
        }
      },
      "Yohkoh": {
        "SXT": {
          "AlMgMn": {
            "end": "2001-12-14 20:58:33",
            "layeringOrder": 1,
            "nickname": "SXT AlMgMn",
            "sourceId": 33,
            "start": "1991-09-13 21:53:40",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "Yohkoh"
              },
              {
                "label": "Instrument",
                "name": "SXT"
              },
              {
                "label": "Filter",
                "name": "AlMgMn"
              }
            ]
          },
          "thin-Al": {
            "end": "2001-12-14 08:20:43",
            "layeringOrder": 1,
            "nickname": "SXT thin-Al",
            "sourceId": 34,
            "start": "1991-09-13 21:49:24",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "Yohkoh"
              },
              {
                "label": "Instrument",
                "name": "SXT"
              },
              {
                "label": "Measurement",
                "name": "thin-Al"
              }
            ]
          },
          "white-light": {
            "end": "1992-11-13 17:05:32",
            "layeringOrder": 1,
            "nickname": "SXT white-light",
            "sourceId": 35,
            "start": "1991-09-11 23:02:54",
            "uiLabels": [
              {
                "label": "Observatory",
                "name": "Yohkoh"
              },
              {
                "label": "Instrument",
                "name": "SXT"
              },
              {
                "label": "Measurement",
                "name": "white-light"
              }
            ]
          }
        }
      }
    }


Example: Get Data Sources Verbose (JSON)
~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~

Output the hierarchical list of available datasources in a format that is
compatible with the JHelioviewer desktop client.

.. code-block::
    :caption: Example Request:

    https://api.helioviewer.org/v2/getDataSources/?verbose=true&enable=[Yohkoh,STEREO_A,STEREO_B]

.. code-block::
    :caption: Example Response:

    {
      "SDO": {
        "children": {
          "AIA": {
            "children": {
              "131": {
                "description": "131 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:43:44",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "131 Å",
                "nickname": "AIA 131",
                "sourceId": 9,
                "start": "2010-06-02 00:05:34"
              },
              "1600": {
                "description": "1600 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:49:28",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "1600 Å",
                "nickname": "AIA 1600",
                "sourceId": 15,
                "start": "2010-06-02 00:05:30"
              },
              "1700": {
                "description": "1700 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:50:30",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "1700 Å",
                "nickname": "AIA 1700",
                "sourceId": 16,
                "start": "2010-06-23 00:00:31"
              },
              "171": {
                "default": true,
                "description": "171 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:44:47",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "171 Å",
                "nickname": "AIA 171",
                "sourceId": 10,
                "start": "2010-06-02 00:05:36"
              },
              "193": {
                "description": "193 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:45:42",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "193 Å",
                "nickname": "AIA 193",
                "sourceId": 11,
                "start": "2010-06-02 00:05:31"
              },
              "211": {
                "description": "211 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:46:35",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "211 Å",
                "nickname": "AIA 211",
                "sourceId": 12,
                "start": "2010-06-02 00:05:37"
              },
              "304": {
                "description": "304 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:48:43",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "304 Å",
                "nickname": "AIA 304",
                "sourceId": 13,
                "start": "2010-06-02 00:05:39"
              },
              "335": {
                "description": "335 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:49:38",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "335 Å",
                "nickname": "AIA 335",
                "sourceId": 14,
                "start": "2010-06-02 00:05:28"
              },
              "4500": {
                "description": "4500 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:00:07",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "4500 Å",
                "nickname": "AIA 4500",
                "sourceId": 17,
                "start": "2010-06-02 00:05:44"
              },
              "94": {
                "description": "94 Ångström extreme ultraviolet",
                "end": "2013-12-05 13:43:01",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "94 Å",
                "nickname": "AIA 94",
                "sourceId": 8,
                "start": "2010-06-02 00:05:33"
              }
            },
            "default": true,
            "description": "Atmospheric Imaging Assembly",
            "label": "Instrument",
            "name": "AIA"
          },
          "HMI": {
            "children": {
              "continuum": {
                "description": "Intensitygram",
                "end": "2013-12-05 11:20:40",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "Continuum",
                "nickname": "HMI Int",
                "sourceId": 18,
                "start": "2010-12-06 06:53:41"
              },
              "magnetogram": {
                "description": "Magnetogram",
                "end": "2013-12-05 12:18:25",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "Magnetogram",
                "nickname": "HMI Mag",
                "sourceId": 19,
                "start": "2010-12-06 06:53:41"
              }
            },
            "description": "Helioseismic and Magnetic Imager",
            "label": "Instrument",
            "name": "HMI"
          }
        },
        "default": true,
        "description": "Solar Dynamics Observatory",
        "label": "Observatory",
        "name": "SDO"
      },
      "SOHO": {
        "children": {
          "EIT": {
            "children": {
              "171": {
                "description": "171 Ångström extreme ultraviolet",
                "end": "2013-08-07 13:00:13",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "171 Å",
                "nickname": "EIT 171",
                "sourceId": 0,
                "start": "1996-01-15 21:39:21"
              },
              "195": {
                "description": "195 Ångström extreme ultraviolet",
                "end": "2013-08-07 01:13:50",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "195 Å",
                "nickname": "EIT 195",
                "sourceId": 1,
                "start": "1996-01-15 20:51:47"
              },
              "284": {
                "description": "284 Ångström extreme ultraviolet",
                "end": "2013-08-07 13:06:09",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "284 Å",
                "nickname": "EIT 284",
                "sourceId": 2,
                "start": "1996-01-15 21:04:17"
              },
              "304": {
                "description": "304 Ångström extreme ultraviolet",
                "end": "2013-08-07 01:19:42",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "304 Å",
                "nickname": "EIT 304",
                "sourceId": 3,
                "start": "1996-01-15 22:00:17"
              }
            },
            "description": "Extreme ultraviolet Imaging Telescope",
            "label": "Instrument",
            "name": "EIT"
          },
          "LASCO": {
            "children": {
              "C2": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-12-05 07:12:05",
                    "label": "Measurement",
                    "layeringOrder": 2,
                    "name": "White Light",
                    "nickname": "LASCO C2",
                    "sourceId": 4,
                    "start": "1996-04-01 01:12:15"
                  }
                },
                "description": "Coronograph 2",
                "label": "Detector",
                "name": "C2"
              },
              "C3": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-12-05 07:18:05",
                    "label": "Measurement",
                    "layeringOrder": 3,
                    "name": "White Light",
                    "nickname": "LASCO C3",
                    "sourceId": 5,
                    "start": "1996-04-14 09:48:18"
                  }
                },
                "description": "Coronograph 3",
                "label": "Detector",
                "name": "C3"
              }
            },
            "description": "The Large Angle Spectrometric Coronagraph",
            "label": "Instrument",
            "name": "LASCO"
          },
          "MDI": {
            "children": {
              "continuum": {
                "description": "Intensitygram",
                "end": "2011-01-11 22:39:00",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "Continuum",
                "nickname": "MDI Int",
                "sourceId": 7,
                "start": "1996-05-19 19:08:35"
              },
              "magnetogram": {
                "description": "Magnetogram",
                "end": "2011-01-11 22:39:00",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "Magnetogram",
                "nickname": "MDI Mag",
                "sourceId": 6,
                "start": "1996-04-21 00:30:04"
              }
            },
            "description": "Michelson Doppler Imager",
            "label": "Instrument",
            "name": "MDI"
          }
        },
        "description": "Solar and Heliospheric Observatory",
        "label": "Observatory",
        "name": "SOHO"
      },
      "STEREO_A": {
        "children": {
          "SECCHI": {
            "children": {
              "COR1": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-12-01 09:50:00",
                    "label": "Measurement",
                    "layeringOrder": 2,
                    "name": "White Light",
                    "nickname": "COR1-A",
                    "sourceId": 28,
                    "start": "2010-01-01 00:05:00"
                  }
                },
                "description": "Coronograph 1",
                "label": "Detector",
                "name": "COR1"
              },
              "COR2": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-11-30 23:54:00",
                    "label": "Measurement",
                    "layeringOrder": 3,
                    "name": "White Light",
                    "nickname": "COR2-A",
                    "sourceId": 29,
                    "start": "2010-01-01 00:24:00"
                  }
                },
                "description": "Coronograph 2",
                "label": "Detector",
                "name": "COR2"
              },
              "EUVI": {
                "children": {
                  "171": {
                    "description": "171 Ångström extreme ultraviolet",
                    "end": "2013-11-30 22:14:00",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "171 Å",
                    "nickname": "EUVI-A 171",
                    "sourceId": 20,
                    "start": "2010-01-01 00:14:00"
                  },
                  "195": {
                    "description": "195 Ångström extreme ultraviolet",
                    "end": "2013-11-30 23:55:30",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "195 Å",
                    "nickname": "EUVI-A 195",
                    "sourceId": 21,
                    "start": "2010-01-01 00:05:30"
                  },
                  "284": {
                    "description": "284 Ångström extreme ultraviolet",
                    "end": "2013-11-30 22:16:30",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "284 Å",
                    "nickname": "EUVI-A 284",
                    "sourceId": 22,
                    "start": "2010-01-01 00:16:30"
                  },
                  "304": {
                    "description": "304 Ångström extreme ultraviolet",
                    "end": "2013-11-30 23:56:15",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "304 Å",
                    "nickname": "EUVI-A 304",
                    "sourceId": 23,
                    "start": "2010-01-01 00:06:15"
                  }
                },
                "description": "Extreme Ultraviolet Imager",
                "label": "Detector",
                "name": "EUVI"
              }
            },
            "description": "Sun Earth Connection Coronal and Heliospheric Investigation",
            "label": "Instrument",
            "name": "SECCHI"
          }
        },
        "description": "Solar Terrestrial Relations Observatory Ahead",
        "label": "Observatory",
        "name": "STEREO_A"
      },
      "STEREO_B": {
        "children": {
          "SECCHI": {
            "children": {
              "COR1": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-12-01 03:51:00",
                    "label": "Measurement",
                    "layeringOrder": 2,
                    "name": "White Light",
                    "nickname": "COR1-B",
                    "sourceId": 30,
                    "start": "2010-01-01 00:05:37"
                  }
                },
                "description": "Coronograph 1",
                "label": "Detector",
                "name": "COR1"
              },
              "COR2": {
                "children": {
                  "white-light": {
                    "description": "White Light",
                    "end": "2013-11-30 23:55:00",
                    "label": "Measurement",
                    "layeringOrder": 3,
                    "name": "White Light",
                    "nickname": "COR2-B",
                    "sourceId": 31,
                    "start": "2010-01-01 00:24:37"
                  }
                },
                "description": "Coronograph 2",
                "label": "Detector",
                "name": "COR2"
              },
              "EUVI": {
                "children": {
                  "171": {
                    "description": "171 Ångström extreme ultraviolet",
                    "end": "2013-11-30 22:15:00",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "171 Å",
                    "nickname": "EUVI-B 171",
                    "sourceId": 24,
                    "start": "2010-01-01 00:07:52"
                  },
                  "195": {
                    "description": "195 Ångström extreme ultraviolet",
                    "end": "2013-11-30 23:56:30",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "195 Å",
                    "nickname": "EUVI-B 195",
                    "sourceId": 25,
                    "start": "2010-01-01 00:06:07"
                  },
                  "284": {
                    "description": "284 Ångström extreme ultraviolet",
                    "end": "2013-11-30 22:17:30",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "284 Å",
                    "nickname": "EUVI-B 284",
                    "sourceId": 26,
                    "start": "2010-01-01 00:07:07"
                  },
                  "304": {
                    "description": "304 Ångström extreme ultraviolet",
                    "end": "2013-11-30 23:57:15",
                    "label": "Measurement",
                    "layeringOrder": 1,
                    "name": "304 Å",
                    "nickname": "EUVI-B 304",
                    "sourceId": 27,
                    "start": "2010-01-01 00:06:52"
                  }
                },
                "description": "Extreme Ultraviolet Imager",
                "label": "Detector",
                "name": "EUVI"
              }
            },
            "description": "Sun Earth Connection Coronal and Heliospheric Investigation",
            "label": "Instrument",
            "name": "SECCHI"
          }
        },
        "description": "Solar Terrestrial Relations Observatory Behind",
        "label": "Observatory",
        "name": "STEREO_B"
      },
      "Yohkoh": {
        "children": {
          "SXT": {
            "children": {
              "AlMgMn": {
                "description": "Al/Mg/Mn filter (2.4 Å - 32 Å pass band)",
                "end": "2001-12-14 20:58:33",
                "label": "Filter",
                "layeringOrder": 1,
                "name": "AlMgMn",
                "nickname": "SXT AlMgMn",
                "sourceId": 33,
                "start": "1991-09-13 21:53:40"
              },
              "thin-Al": {
                "description": "11.6 μm Al filter (2.4 Å - 13 Å pass band)",
                "end": "2001-12-14 08:20:43",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "Thin Al",
                "nickname": "SXT thin-Al",
                "sourceId": 34,
                "start": "1991-09-13 21:49:24"
              },
              "white-light": {
                "description": "No filter",
                "end": "1992-11-13 17:05:32",
                "label": "Measurement",
                "layeringOrder": 1,
                "name": "White Light",
                "nickname": "SXT white-light",
                "sourceId": 35,
                "start": "1991-09-11 23:02:54"
              }
            },
            "description": "Soft X-ray Telescope",
            "label": "Instrument",
            "name": "SXT"
          }
        },
        "description": "Yohkoh (Solar-A)",
        "label": "Observatory",
        "name": "Yohkoh"
      }
    }

Output the hierarchical list of available datasources in a format that is
compatible with the JHelioviewer desktop client.
