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