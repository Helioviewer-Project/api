from argparse import ArgumentParser, Namespace
from pathlib import Path
import json
from jsonschema import validators, validate
from referencing import Registry, Resource
from referencing.exceptions import NoSuchResource

def parse_args() -> Namespace:
    parser = ArgumentParser(description="Validates a json file against a json schema")
    parser.add_argument("schema", type=str, help="JSON Schema file to validate against")
    parser.add_argument("json", type=str, help="JSON file to validate")
    return parser.parse_args()

def load_json(json_file: str) -> dict:
    with open(json_file, "r") as fp:
        return json.load(fp)

def retrieve_from_filesystem(uri: str):
    api_prefix = "https://api.helioviewer.org/schema/"
    schema_path = Path("../../docroot/schema/")
    if not uri.startswith(api_prefix):
        raise NoSuchResource(ref=uri)
    path = schema_path / Path(uri.removeprefix(api_prefix))
    contents = json.loads(path.read_text())
    return Resource.from_contents(contents)

def validate_schema(json: dict, schema: dict):
    validator_class = validators.validator_for(schema)
    # Registry which maps api.helioviewer.org paths to local ones
    local_registry = Registry(retrieve=retrieve_from_filesystem)
    validator = validator_class(schema, registry=local_registry)
    validator.validate(json)

if __name__ == "__main__":
    args = parse_args()
    schema = load_json(args.schema)
    json_file = load_json(args.json)
    validate_schema(json_file, schema)