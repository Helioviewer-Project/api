# Using requests instead of hapiclient because the flare scoreboard doesn't accept Z at the end of the ISO timestamp, and hapiclient forces the use of Z
import requests
import pyarg
from prediction import Prediction

SCOREBOARD_URL = "https://iswa.gsfc.nasa.gov/IswaSystemWebApp/flarescoreboard/hapi"

# The values here align with the database IDs
REGIONAL_DATASETS = {
     "SIDC_Operator_REGIONS" : 1 ,
     "BoM_flare1_REGIONS"    : 2 ,
     "AMOS_v1_REGIONS"       : 5 ,
     "ASAP_1_REGIONS"        : 6 ,
     "MAG4_LOS_FEr_REGIONS"  : 7 ,
     "MAG4_LOS_r_REGIONS"    : 8 ,
     "MAG4_SHARP_FE_REGIONS" : 9 ,
     "MAG4_SHARP_REGIONS"    : 10,
     "MAG4_SHARP_HMI_REGIONS": 11,
     "AEffort_REGIONS"       : 12
}

def get_dataset_id(dataset_name: str) -> int:
    return REGIONAL_DATASETS[dataset_name]

def get_all(start: str, stop: str) -> list:
    """
    Returns a list of all available predictions in the given time range across all datasets
    """
    all_predictions = []
    for dataset in REGIONAL_DATASETS.keys():
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