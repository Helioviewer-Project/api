# Using requests instead of hapiclient because the flare scoreboard doesn't accept Z at the end of the ISO timestamp, and hapiclient forces the use of Z
import requests
import pyarg
from prediction import Prediction

SCOREBOARD_URL = "https://iswa.gsfc.nasa.gov/IswaSystemWebApp/flarescoreboard/hapi"

# IMPORTANT! The order of datasets here must match the order of datasets in the database so that the correct dataset_id is given.
REGIONAL_DATASETS = [
    "SIDC_Operator_REGIONS",
    "BoM_flare1_REGIONS",
    "AMOS_v1_REGIONS",
    "ASAP_1_REGIONS",
    "MAG4_LOS_FEr_REGIONS",
    "MAG4_LOS_r_REGIONS",
    "MAG4_SHARP_FE_REGIONS",
    "MAG4_SHARP_REGIONS",
    "MAG4_SHARP_HMI_REGIONS",
    "AEffort_REGIONS"
];

def get_dataset_id(dataset: str) -> int:
    """
    Returns the ID of the given dataset
    """
    assert dataset in REGIONAL_DATASETS, "Invalid dataset: " + dataset
    return REGIONAL_DATASETS.index(dataset) + 1

def get_all(start: str, stop: str) -> list:
    """
    Returns a list of all available predictions in the given time range across all datasets
    """
    all_predictions = []
    for dataset in REGIONAL_DATASETS:
        all_predictions += get_predictions(dataset, start, stop)
    return all_predictions

def get_predictions(dataset: str, start: str, stop: str) -> list:
    """
    Returns a list of predictions for the given dataset in the given time range
    """
    assert dataset in REGIONAL_DATASETS, "Invalid dataset: " + dataset
    data = requests.get(SCOREBOARD_URL + "/data", params={
        "id": dataset,
        "time.min": start,
        "time.max": stop,
        "format": "json"
    })
    return normalize_predictions(data.json(), dataset)

def normalize_predictions(predictions: dict, dataset: str) -> list:
    """
    Normalizes predictions so that they all have the same fields
    """
    parameters = predictions["parameters"]
    results = []
    for record in predictions['data']:
        results.append(Prediction(record, parameters, dataset))
    return results

def print_predictions(predictions: list):
    for prediction in predictions:
        print(prediction)

if __name__ == "__main__":
    args = pyarg.parse_args("Retrieves flare predictions from the CCMC Flare Scoreboard", [
        [['dataset'], {'help': 'Dataset to retrieve', 'type': str, 'choices': REGIONAL_DATASETS}],
        [['start'], {'help': 'Start time', 'type': str}],
        [['stop'], {'help': 'Stop time', 'type': str}],
    ])
    print_predictions(get_predictions(args.dataset, args.start, args.stop))