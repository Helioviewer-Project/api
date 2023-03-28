import configparser
import os

import flare_scoreboard
import pyarg
import MySQLdb as mysqld


def get_config():
    """Load configuration file"""
    config = configparser.ConfigParser()

    basedir = os.path.dirname(os.path.realpath(__file__))
    default_userconfig = os.path.join(basedir, '..', '..', '..', 'install', 'settings','settings.cfg')
    if os.path.isfile(default_userconfig):
        config.read_file(open(default_userconfig))
    else:
        config.read_file(open(os.path.join(basedir, '..', '..', '..', 'install', 'settings', 'settings.example.cfg')))

    return config

def import_predictions(start:str , end: str):
    print("Importing predictions from {} to {}".format(start, end))
    predictions = flare_scoreboard.get_all(start, end)
    print("Found {} predictions".format(len(predictions)))
    insert_predictions_into_db(predictions)

def get_db():
    config = get_config()
    db = mysqld.connect(host=config['database']['dbhost'],
                        user=config['database']['dbuser'],
                        passwd=config['database']['dbpass'],
                        db=config['database']['dbname'])
    return db

def insert_predictions_into_db(predictions: list):
    db = get_db()
    cursor = db.cursor()
    data = as_list(predictions)
    result = cursor.executemany("REPLACE INTO `flare_predictions` (`dataset_id`, `start_window`, `end_window`, `issue_time`, `c`, `m`, `x`, `cplus`, `mplus`, `latitude`, `longitude`, `hpc_x`, `hpc_y`, `sha256`) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)",
                          data)
    cursor.close()
    db.commit()

def getattr_or_none(obj, name):
    return getattr(obj, name, None)

def as_list(predictions):
    """
    Converts a list of predictions into items that can be inserted into the database
    """
    result = []
    ignore_count = 0
    import pdb; pdb.set_trace()
    for prediction in predictions:
        # Ignore predictions without a position
        if prediction.latitude and prediction.longitude:
            dataset_id = flare_scoreboard.get_dataset_id(prediction.dataset)
            result.append((dataset_id,
                           prediction.start_window,
                           prediction.end_window,
                           prediction.issue_time,
                           getattr_or_none(prediction, 'C'),
                           getattr_or_none(prediction, 'M'),
                           getattr_or_none(prediction, 'X'),
                           getattr_or_none(prediction, 'CPlus'),
                           getattr_or_none(prediction, 'MPlus'),
                           prediction.latitude,
                           prediction.longitude,
                           prediction.hpc_x,
                           prediction.hpc_y,
                           prediction.sha256))
        else:
            ignore_count += 1
    print(f"Dropped {ignore_count} predictions due to missing latitude/longitude position")
    return result

def main():
    args = pyarg.parse_args("Imports CCMC Flare Predictions over the given time range into the Helioviewer Database", [
        [['start'], {'help': 'Start time of the time range in format 2023-03-01T00:00:00', 'type': str}],
        [['end'], {'help': 'End time of the time range in format 2023-03-01T00:00:00', 'type': str}],
    ])
    import_predictions(args.start, args.end)

if __name__ == "__main__":
    main()