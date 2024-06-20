# Schema
This directory contains scripts for generating a json schemas

## validate.py
Small program to test a schema against a json file.

Usage: `python validate.py schema_file json_file`

## image_schema.py
Creates `image_layer.schema.json` from the given template file.
Generally, this fills in the available enum values using info from
the database.
The template file should have the following template parameters which will
be replaced with the appropriate strings.

| String | Turns Into |
| ------ | ---------- |
| {{UILABEL_NAMES}} | List of all possible label names |
| {{UILABEL_LABELS}} | List of all possible labels |
| {{UILABEL_LABEL_PATTERN}} | Regex pattern for all possible labels |
| {{DATA_NICKNAMES}} | List of all possible data source names |


## requirements.txt
Combined requirements for `validate.py` and `image_schema.py`

## `*.schema.template.json`
JSON schema templates.

## `deploy.sh`
Runs python scripts to generate schema files from template files and copies
them to `docroot/schema`.
