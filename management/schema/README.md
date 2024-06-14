# Schema
This directory contains scripts for generating a json schemas

## validate.py
Small program to test a schema against a json file.

Usage: `python validate.py schema_file json_file`

## image_schema.py
Creates `image_layer.schema.json` from the given template file.
This replaces the following strings in the template file:

| String | Turns Into |
| ------ | ---------- |
| {{UILABEL_NAMES}} | List of all possible label names |
| {{UILABEL_LABELS}} | List of all possible labels |
| {{UILABEL_LABEL_PATTERN}} | Regex pattern for all possible labels |

Fills in the available enum values from the helioviewer database

## requirements.txt
Combined requirements for `validate.py` and `image_schema.py`

## `*.schema.json`
JSON schema's maintained by us.

## `deploy.sh`
Copies all files with "*.schema.json" to the API's `docroot/schema` folder.
