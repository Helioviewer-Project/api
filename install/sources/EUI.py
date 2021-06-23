
from sunpy.map import GenericMap

class EUIMap(GenericMap):

    def __init__(self, data, header, **kwargs):
        GenericMap.__init__(self, data, header, **kwargs)

        self.meta["detector"] = header.get("detector").split("_")[0]
        self.meta["obsrvtry"] = "SOLO"

    @classmethod
    def is_datasource_for(cls, data, header, **kwargs):
        return header.get("instrume") == "EUI"
