from argparse import ArgumentParser, Namespace
import json
from jsonschema import validate

def parse_args() -> Namespace:
    parser = ArgumentParser(description="Validates a json file against a json schema")
    parser.add_argument("schema", type=str, help="JSON Schema file to validate against")
    parser.add_argument("json", type=str, help="JSON file to validate")
    return parser.parse_args()

def load_json(json_file: str) -> dict:
    with open(json_file, "r") as fp:
        return json.load(fp)

if __name__ == "__main__":
    args = parse_args()
    schema = load_json(args.schema)
    json_file = load_json(args.json)
    validate(json_file, schema)