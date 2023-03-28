# CCMC
These scripts work together to create a CLI interface for importing CCMC Flare Predictions into Helioviewer.

# Usage
```
python import_predictions <start_time> <end_time>
```

Dates must conform to the format `2023-01-01T00:00:00`.
These dates aren't processed and they're passed directly to the CCMC API, so formatting is strict.

# Listing
## `import_predictions.py`
CLI for importing predictions.

See usage above

## `flare_scoreboard.py`
Provides an interface for querying flare predictions.

Can be used as a standalone program to list a set of predictions.
Run with `-h` to see options.

## `prediction.py`
Implements a class that wraps around a record returned from the HAPI server to provide a more convenient way of accessing data.
HAPI records are returned as arrays of data values without any associated metadata to tell you what each value represents.
The `Prediction` class implemented here allows you to access the record's data via its field names directly.

## `pyarg.py`
Convenience wrapper around argparse to create the argparse object, add the program description, arguments, and parse input args all in one function call.
