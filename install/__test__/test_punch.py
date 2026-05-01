import unittest
import datetime
from helioviewer.hvpull.servers.punch import PUNCHDataServer

class TestPunchDataServer(unittest.TestCase):
  def test_compute_directories(self):
    punch = PUNCHDataServer()
    dirs = punch.compute_directories(datetime.datetime(2026, 1, 1), datetime.datetime(2026, 1, 5))
    assert dirs == [
      'https://umbra.nascom.nasa.gov/punch/L/3/CAM/2026/01/05',
      'https://umbra.nascom.nasa.gov/punch/L/3/PAM/2026/01/05',
      'https://umbra.nascom.nasa.gov/punch/L/3/CAM/2026/01/04',
      'https://umbra.nascom.nasa.gov/punch/L/3/PAM/2026/01/04',
      'https://umbra.nascom.nasa.gov/punch/L/3/CAM/2026/01/03',
      'https://umbra.nascom.nasa.gov/punch/L/3/PAM/2026/01/03',
      'https://umbra.nascom.nasa.gov/punch/L/3/CAM/2026/01/02',
      'https://umbra.nascom.nasa.gov/punch/L/3/PAM/2026/01/02',
      'https://umbra.nascom.nasa.gov/punch/L/3/CAM/2026/01/01',
      'https://umbra.nascom.nasa.gov/punch/L/3/PAM/2026/01/01',
    ]

  def test_get_datetime_from_file(self):
    punch = PUNCHDataServer()
    assert punch.get_datetime_from_file("PUNCH_L3_PAM_20251102001600_v0j.fits") == datetime.datetime(2025, 11, 2, 0, 16, 0)
    assert punch.get_datetime_from_file("PUNCH_L3_CAM_20260221001600_v0j.fits") == datetime.datetime(2026, 2, 21, 0, 16, 0)
