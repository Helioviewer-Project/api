from argparse import ArgumentParser, ArgumentTypeError
from configparser import ConfigParser
from datetime import datetime
import MySQLdb
import os
import shutil
import subprocess
import sys

# Images from disk will be moved here in case they need to be restored.
IMAGE_BACKUP_DIR = "/tmp/refill"
PROGRAM_DESCRIPTION = """Deletes existing images from the database and from disk, then redownloads them from the source.


Example: Remove all AIA 94 and AIA 171 images between 2023-01-01 00:00:00 and 2023-01-02 00:00:00

python refill.py -d lmsal -s "2023-01-01 00:00:00" -e "2023-01-02 00:00:00" --sources 8 10 --config "../../install/settings/settings.cfg"
This specifies that after removing the images for source IDs 8 and 10 between the given time range, then downloader.py will start with the lmsal option over that same time range.
"""

def format_date(date: datetime) -> str:
    return date.strftime("%Y-%m-%d %H:%M:%S")

def load_config(path: str) -> ConfigParser:
    parser = ConfigParser()
    parser.read(path)
    return parser

def valid_date(s: str) -> datetime:
    try:
        return datetime.strptime(s, "%Y-%m-%d %H:%M:%S")
    except ValueError:
        msg = "not a valid date: {0!r}".format(s)
        raise ArgumentTypeError(msg)

def get_db_instance(config: ConfigParser):
    # Establish a connection
    connection = MySQLdb.connect(
        host=config['database']['dbhost'],
        user=config['database']['dbuser'],
        passwd=config['database']['dbpass'],
        db=config['database']['dbname']
    )
    return connection

def parse_args():
    parser = ArgumentParser()
    parser.description = PROGRAM_DESCRIPTION
    parser.add_argument("-s", "--start", help="Beginning of time range", required=True, type=valid_date)
    parser.add_argument("-e", "--end", help="End of time range", required=True, type=valid_date)
    parser.add_argument("--server", help="Upstream source. See downloader.py's help for options", required=True)
    parser.add_argument("--downloader", help="Path to downloader.py", required=True)
    parser.add_argument("--sources", nargs="+", help="List of source ids for data to be removed", required=True, type=int)
    parser.add_argument("-c", "--config", help="Path to hv config with db credentials", type=str, required=True)
    return parser.parse_args()

def main():
    args = parse_args()
    config = load_config(args.config)
    delete_existing_files(args.sources, args.start, args.end, config)
    download_files(args.start, args.end, args.server, args.downloader)

def delete_existing_files(sources: list, start: datetime, end: datetime, config: ConfigParser) -> list:
    """
    Delete existing files from the database and from disk.
    """
    db = get_db_instance(config)
    cursor = db.cursor()
    start_str = format_date(start)
    end_str = format_date(end)

    # Get all the files that are going to be removed from the database so that they can be backed up
    files = get_files_to_remove(config, cursor, sources, start_str, end_str)
    # delete files is interactive, and the user may cancel the operation
    data_was_deleted = remove_data_rows(cursor, sources, start_str, end_str)
    cursor.close()
    db.close()

    if data_was_deleted:
        backup_files(files)
    else:
        # If the user cancelled the operation. Then stop here. Nothing more to do.
        sys.exit(0)

def backup_files(files: list):
    """
    Moves the given list of files to the image backup dir
    """
    if not os.path.exists(IMAGE_BACKUP_DIR):
        os.mkdir(IMAGE_BACKUP_DIR)
    for fname in files:
        shutil.move(fname, IMAGE_BACKUP_DIR)

def download_files(start: datetime, end: datetime, server: str, script: str):
    """
    Starts downloader.py with the given options
    """
    start_str = format_date(start)
    end_str = format_date(end)
    subprocess.run(
        [sys.executable,
         script,
         '-d',
         server,
         '-s',
         start_str,
         '-e',
         end_str]
    )

def get_files_to_remove(config: ConfigParser, cursor, sources: list, start_date: str, end_date: str) -> list:
    """
    Queries the database to get the list of files that will be removed by the operation
    """
    select_query = "SELECT filepath, filename FROM data WHERE sourceId IN ({}) AND date BETWEEN %s AND %s".format(",".join(["%s"] * len(sources)))
    cursor.execute(select_query, sources + [start_date, end_date])
    rows = cursor.fetchall()
    file_list = []
    base_path = config['directories']['image_archive']
    for row in rows:
        filepath, filename = row
        file_list.append(base_path + filepath + "/" + filename)
    return file_list

def remove_data_rows(cursor, sources: list, start_date: str, end_date: str) -> bool:
    """
    Deletes files for the given sources over the given time range
    Returns true if file are removed
    Returns false if the user cancels the operation
    """
    delete_query = "DELETE FROM data WHERE sourceId IN ({}) AND date BETWEEN %s AND %s".format(",".join(["%s"] * len(sources)))
    formatted_query = delete_query % tuple(sources + [start_date, end_date])
    print("Going to execute the following query")
    print(formatted_query)
    response = input("Should I proceed? (y/n): ")
    if (response == "y"):
        cursor.execute(delete_query, sources + [start_date, end_date])
    return response == "y"

if __name__ == "__main__":
    main()