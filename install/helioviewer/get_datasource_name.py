from argparse import ArgumentParser
from db import get_datasources, get_db_cursor
from jp2parser import JP2parser
import configparser
import os

def parse_args():
    parser = ArgumentParser(description="Determines the required data source name for a given file")
    parser.add_argument('jp2_file', type=str, help="JPEG2000 file to examine")
    parser.add_argument('-c', '--config', metavar='config', type=str, help="Config file with database credentials")
    return parser.parse_args()

def extract_datasource_name(jp2_file, cursor):
    leafs = ["observatory", "instrument", "detector", "measurement"]

    sources = get_datasources(cursor)
    source = sources
    prev = ""
    jp2 = JP2parser(jp2_file)
    img = jp2.getData()

    in_db = True # Track if the datasource is in the database
    for leaf in leafs:
        print("{}: {}".format(leaf, img[leaf]))

        # Check if the found field is in the database
        if in_db:
            try:
                if img[leaf] != prev:
                    # If the field is in the source dictionary, then
                    # it is in the database
                    source = source[str(img[leaf])]
                prev = img[leaf]
            except KeyError:
                # If a KeyError occurs, it's because the item was not found in the database
                print("{} not found in database".format(img[leaf]))
                in_db = False

def get_config(filepath):
    """Load configuration file"""
    config = configparser.ConfigParser()
    
    basedir = os.path.dirname(os.path.realpath(__file__))
    default_userconfig = os.path.join(basedir, '..', 'settings/settings.cfg')
    
    if filepath is not None and os.path.isfile(filepath):
        config.readfp(open(filepath))
    elif os.path.isfile(default_userconfig):
        config.readfp(open(default_userconfig))
    else:
        config.readfp(open(os.path.join(basedir, 'settings/settings.example.cfg')))

    return config

def get_db_with_config(cfg):
    conf = get_config(cfg)
    dbhost = conf.get('database', 'dbhost')
    dbname = conf.get('database', 'dbname')
    dbuser = conf.get('database', 'dbuser')
    dbpass = conf.get('database', 'dbpass')
    db, cursor = get_db_cursor(dbhost, dbname, dbuser, dbpass)
    return cursor

def main():
    args = parse_args()
    print(args)
    cursor = get_db_with_config(args.config)

    extract_datasource_name(args.jp2_file, cursor)

if __name__ == "__main__":
    main()
