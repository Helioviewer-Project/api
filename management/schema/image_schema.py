from argparse import ArgumentParser, Namespace
import configparser
import os
import sys
sys.path.append("../../install/helioviewer")
from db import get_datasources, get_db_cursor

SQL_LABELS = "SELECT DISTINCT label FROM datasource_property"
SQL_NAMES = "SELECT DISTINCT name FROM datasource_property"
SQL_NICKNAMES = "SELECT name FROM datasources"

def parse_args() -> Namespace:
    parser = ArgumentParser(description="Generates the image_layer json schema from a template")
    parser.add_argument("template", type=str, help="Template file")
    parser.add_argument('-c', '--config', metavar='config', type=str, help="Config file with database credentials")
    return parser.parse_args()

def get_config(filepath):
    """Load configuration file"""
    config = configparser.ConfigParser()

    filedir = os.path.dirname(os.path.realpath(__file__))
    basedir = os.path.join(filedir, '..', '..', 'install')
    default_userconfig = os.path.join(basedir, 'settings/settings.cfg')

    if filepath is not None and os.path.isfile(filepath):
        config.read(filepath)
    elif os.path.isfile(default_userconfig):
        config.read(default_userconfig)
    else:
        config.read(os.path.join(basedir, 'settings/settings.example.cfg'))

    return config


def get_db_with_config(cfg):
    conf = get_config(cfg)
    dbhost = conf.get('database', 'dbhost')
    dbname = conf.get('database', 'dbname')
    dbuser = conf.get('database', 'dbuser')
    dbpass = conf.get('database', 'dbpass')
    db, cursor = get_db_cursor(dbhost, dbname, dbuser, dbpass)
    return db, cursor

class DatabaseLookup:
    def __init__(self, cfg):
        self.db, self.cursor = get_db_with_config(cfg)
        self._labels = None
        self._names = None
        self._nicknames = None

    def _select_column(self, select: str) -> list[str]:
        """
        Runs the given SQL and returns the first column as a list
        """
        self.cursor.execute(select)
        return list(map(lambda x: x[0], self.cursor.fetchall()))

    def get_labels(self) -> list[str]:
        if self._labels is not None:
            return self._labels
        self._labels = self._select_column(SQL_LABELS)
        return self._labels

    def get_names(self) -> list[str]:
        if self._names is not None:
            return self._names
        self._names = self._select_column(SQL_NAMES)
        return self._names

    def get_nicknames(self) -> list[str]:
        if self._nicknames is not None:
            return self._nicknames
        self._nicknames = self._select_column(SQL_NICKNAMES)
        return self._nicknames

    def get_label_pattern(self) -> str:
        labels = self.get_labels()
        return "(" + "|".join(labels) + ")"

    def get_label_string(self) -> str:
        labels = self.get_labels()
        return ",".join(map(lambda x: '"%s"' % x, labels))

    def get_name_string(self) -> str:
        names = self.get_names()
        return ",".join(map(lambda x: '"%s"' % x, names))

    def get_nicknames_string(self) -> str:
        nicknames = self.get_nicknames()
        return ",".join(map(lambda x: '"%s"' % x, nicknames))

def process_template(template, labels, label_pattern, label_names, nicknames):
    with open(template, "r") as fp:
        schema = fp.read()
    schema = schema.replace("{{UILABEL_NAMES}}", label_names)
    schema = schema.replace("{{UILABEL_LABELS}}", labels)
    schema = schema.replace("{{UILABEL_LABEL_PATTERN}}", label_pattern)
    schema = schema.replace("{{DATA_NICKNAMES}}", nicknames)
    return schema

if __name__ == "__main__":
    args = parse_args()
    db = DatabaseLookup(args.config)
    label_string = db.get_label_string()
    name_string = db.get_name_string()
    patterns = db.get_label_pattern()
    nicknames = db.get_nicknames_string()
    print(process_template(args.template, label_string, patterns, name_string, nicknames))