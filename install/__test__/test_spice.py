import datetime
import os

import pytest

from helioviewer.hvpull.net.daemon import ImageRetrievalDaemon
from helioviewer.hvpull.servers.spice import SpiceDataServer
from helioviewer.jp2parser import JP2parser


class FakeImageData:
    observatory = "Solar Orbiter"
    instrument = "SPICE"
    detector = "SW"
    nickname = "SPICE"
    measurement = "103.19"
    meta = {"cmpnam": "O VI 103.19 nm"}
    date = datetime.datetime(2023, 12, 2, 6, 19, 22)


def test_spice_remote_root():
    spice = SpiceDataServer()

    assert spice.uri == "https://helioviewer.ias.u-psud.fr/jp2/SPICE/"
    assert spice.name == "SPICE"
    assert spice.pause == datetime.timedelta(minutes=60)


def test_compute_directories():
    spice = SpiceDataServer()

    dirs = spice.compute_directories(
        datetime.datetime(2023, 12, 1),
        datetime.datetime(2023, 12, 3),
    )

    channels = [
        "774",
        "103.19",
        "70.38",
        "78.77",
        "70.6",
        "97.25",
        "76.51",
        "97.7",
    ]

    expected = []

    for date in [
        "2023/12/03",
        "2023/12/02",
        "2023/12/01",
    ]:
        for channel in channels:
            expected.append(
                f"https://helioviewer.ias.u-psud.fr/jp2/SPICE/{date}/{channel}"
            )

    assert dirs == expected


def test_get_datetime_from_file():
    spice = SpiceDataServer()

    assert spice.get_datetime_from_file(
        "solo_L4_spice-n-ras_20231202T061922_218104664-000-DR6_7_0.jp2"
    ) == datetime.datetime(2023, 12, 2, 6, 19, 22)

    assert spice.get_datetime_from_file(
        "solo_L4_spice-n-ras_20231202T181921_218104668-000-DR5_5_0.jp2"
    ) == datetime.datetime(2023, 12, 2, 18, 19, 21)


def test_get_datetime_from_invalid_file():
    spice = SpiceDataServer()

    with pytest.raises(
        ValueError,
        match="Unable to extract SPICE datetime",
    ):
        spice.get_datetime_from_file(
            "invalid_spice_filename.jp2"
        )


def test_spice_registered_in_daemon():
    assert ImageRetrievalDaemon.get_servers()["spice"] == "SpiceDataServer"


def test_spice_loads_from_spice_module():
    daemon = ImageRetrievalDaemon.__new__(ImageRetrievalDaemon)

    cls = daemon._load_class(
        "helioviewer.hvpull.servers",
        "spice",
        ImageRetrievalDaemon.get_servers()["spice"],
    )

    assert cls is SpiceDataServer


def test_spice_detection_keys_ignore_detector():
    img = {
        "observatory": "Solar_Orbiter",
        "instrument": "SPICE",
        "detector": "SW",
        "measurement": "intensity",
        "line": "O VI 103.19 nm",
    }

    assert JP2parser.get_detection_keys(img) == [
        "observatory",
        "instrument",
        "measurement",
        "line",
    ]


def test_spice_metadata_extraction(monkeypatch):
    parser = JP2parser.__new__(JP2parser)
    parser._filepath = (
        "solo_L4_spice-n-ras_20231202T061922_218104664-000_0_3.jp2"
    )
    parser._data = {}

    monkeypatch.setattr(
        parser,
        "getImageMap",
        lambda: FakeImageData(),
    )
    monkeypatch.setattr(
        parser,
        "getImageDimensions",
        lambda: {"width": 1024, "height": 1024},
    )
    monkeypatch.setattr(
        parser,
        "getRefPixelCoords",
        lambda: {"x": 512, "y": 512},
    )
    monkeypatch.setattr(
        parser,
        "getImagePlateScale",
        lambda: 1,
    )
    monkeypatch.setattr(
        parser,
        "getDSun",
        lambda: 149597870700,
    )
    monkeypatch.setattr(
        parser,
        "getLayeringOrder",
        lambda: 1,
    )
    monkeypatch.setattr(
        parser,
        "_get_date",
        lambda image_data: image_data.date,
    )
    monkeypatch.setattr(
        parser,
        "get_storage_suffix",
        lambda image: os.path.join(
            image["nickname"],
            "2023/12/02",
            image["measurement"],
        ),
    )

    image = parser.getData()

    assert image["observatory"] == "Solar_Orbiter"
    assert image["instrument"] == "SPICE"
    assert image["detector"] == "SW"
    assert image["measurement"] == "intensity"
    assert image["line"] == "O VI 103.19 nm"


def test_spice_missing_cmpnam_message():
    parser = JP2parser.__new__(JP2parser)
    image_data = FakeImageData()
    image_data.meta = {}

    with pytest.raises(
        ValueError,
        match="SPICE JP2 missing required CMPNAM metadata",
    ):
        parser._get_spice_line(image_data)
