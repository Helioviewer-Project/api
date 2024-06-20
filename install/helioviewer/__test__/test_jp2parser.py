import os
from helioviewer.jp2parser import JP2parser
DIR = os.path.dirname(__file__)

def test_helioviewer_tag():
    """
    Verifies that jp2parser can successfully extracts the Helioviewer tag
    from a sample image.
    """
    parser = JP2parser(os.path.join(DIR, "__tdata__", "2012_07_05__03_25_52_200__RHESSI_RHESSI_Back_Projection_25-50keV.jp2"))
    data = parser.getData()
    assert data["energy_band"] == "25.0_50.0"
    assert data["reconstruction_method"] == "Back_Projection"
    assert parser._helioviewer is not None
    assert parser._helioviewer["HV_RHESSI_FLARE_ID"] == '120705117'

def test_get_dsun():
    """
    Tests that jp2parser can read dsun for several test images
    """
    # List of files to test, and the expected dsun value
    known_answers = {
        os.path.join(DIR, "__tdata__", "2012_07_05__03_25_52_200__RHESSI_RHESSI_Back_Projection_25-50keV.jp2"): 152096710000,
        os.path.join(DIR, "__tdata__", "2024_04_16__11_15_41_129__SDO_AIA_AIA_304.jp2"): 150159030000,
        os.path.join(DIR, "__tdata__", "2024_04_16__13_00_13_908__SOHO_EIT_EIT_171.jp2"): 148557089924.02765,
        os.path.join(DIR, "__tdata__", "iris_1330A_20240415_060002.jp2"): 150094000000,
    }
    # Test each file
    for jp2file in known_answers:
        parser = JP2parser(jp2file)
        dsun = parser.getDSun()
        assert dsun == known_answers[jp2file], "Failed on file %s" % os.path.basename(jp2file)


