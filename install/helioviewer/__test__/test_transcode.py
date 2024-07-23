import os
import helioviewer.jp2 as jp2

def test_transcode():
    # Default transcode
    transcoded = jp2.transcode("/usr/local/bin/kdu_transcode", "/tmp/jp2/2021_06_01__00_01_21_347__SDO_AIA_AIA_171.jp2")
    assert transcoded == "/tmp/jp2/2021_06_01__00_01_21_347__SDO_AIA_AIA_171.jp2.tmp.jp2"
    assert os.path.exists(transcoded)
    os.remove(transcoded)

    # Transcode with precincts
    transcoded = jp2.transcode("/usr/local/bin/kdu_transcode", "/tmp/jp2/2021_06_01__00_01_21_347__SDO_AIA_AIA_171.jp2", cprecincts=[128,128])
    assert transcoded == "/tmp/jp2/2021_06_01__00_01_21_347__SDO_AIA_AIA_171.jp2.tmp.jp2"
    assert os.path.exists(transcoded)
    os.remove(transcoded)
