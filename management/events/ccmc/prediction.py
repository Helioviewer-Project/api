from astropy.coordinates import SkyCoord
from sunpy.coordinates import frames
import astropy.units as u
import json

def hgs2hpc(lat, lon, obstime):
    coord = SkyCoord(lon*u.deg, lat*u.deg, frame=frames.HeliographicStonyhurst, observer="earth", obstime=obstime)
    return coord.transform_to(frames.Helioprojective)

_LATITUDE_FIELDS = ["NOAALatitude", "CataniaLatitude", "ModelLatitude"]
_LONGITUDE_FIELDS = ["NOAALongitude", "CataniaLongitude", "ModelLongitude"]

class Prediction:
    """
    Encapsulates a Flare Scoreboard prediction
    """
    def __init__(self, data: list, parameters: list, dataset: str):
        """
        Creates a new Prediction object
        """
        self.data = data
        self.parameters = parameters
        self.dataset = dataset
        if self.latitude and self.longitude:
            self.hpc = hgs2hpc(self.latitude, self.longitude, self.start_window)

    def __repr__(self):
        return f"{self.dataset} Prediction"

    def __getattr__(self, name: str):
        """
        Returns the value of the given attribute
        """
        # Handle latitude and longitude as special cases
        if name == "latitude":
            return self._get_latitude()
        if name == "longitude":
            return self._get_longitude()
        # Handle case for computed HPC coordinates
        if name == "hpc_x":
            return self.hpc.Tx.value
        if name == "hpc_y":
            return self.hpc.Ty.value
        if name == "sha256":
            return self.gen_sha256()
        if name == "json":
            return self._jsonify()

        for i in range(len(self.parameters)):
            # Attribute only exists if it is not a fill value. Fill is used for values which have no data.
            if self.parameters[i]['name'] == name and self.data[i] != self.parameters[i]['fill']:
                return self.data[i]
        raise AttributeError(f"Attribute {name} does not exist")

    def gen_sha256(self):
        """
        Generates a SHA256 hash of the CSV format of the record
        """
        import hashlib
        msg_to_hash = ",".join([str(x) for x in self.data])
        return hashlib.sha256(msg_to_hash.encode()).hexdigest()

    def _get_latitude(self) -> float:
        """
        Returns the latitude of the prediction
        """
        for field in _LATITUDE_FIELDS:
            if hasattr(self, field):
                return getattr(self, field)

    def _get_longitude(self) -> float:
        """
        Returns the longitude of the prediction
        """
        for field in _LONGITUDE_FIELDS:
            if hasattr(self, field):
                return getattr(self, field)

    def _label_str(self, label: str, value):
        """
        Returns a string representing the given label and value
        """
        return f"{label}: {value}".rjust(10)

    def _jsonify(self):
        """
        Converts the flare prediction hapi data into a json string with parameters as keys
        """
        jsonObject = {}
        for i in range(len(self.parameters)):
            # Attribute only exists if it is not a fill value. Fill is used for values which have no data.
            key = self.parameters[i]['name']
            value = self.data[i]
            jsonObject[key] = value
        return json.dumps(jsonObject)

    def __str__(self):
        return f"{self.dataset} Prediction issued at {self.issue_time} for " + f"({self.latitude},{self.longitude}) (lat,lon).".rjust(22) + self._label_str("c", self.C) + self._label_str("c+", self.CPlus) + self._label_str("m", self.M) + self._label_str("m+", self.MPlus) + self._label_str("x", self.X)
