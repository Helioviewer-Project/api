# Helioviewer

Helioviewer.org is a web application visualization tool for solar physics data based on the JPEG 2000 image compression standard.

To request a new feature or to report a bug, visit 
  
https://github.com/Helioviewer-Project/helioviewer.org/issues

## Running Helioviewer:

To use Helioviewer, it is not neccessary to install any new software, simply visit http://www.helioviewer.org.

## Installation

If you wish to run your own local copy of Helioviewer.org, you have 2 options.
- Through the development [container](https://hub.docker.com/r/dgarciabriseno/helioviewer.org-docker).
- Manual Installation with instructions [here](https://helioviewer-project.github.io/install/).
    
## Development

A `Makefile` wraps the common `docker compose` workflows for the API container.
Run `make help` for the full list. The most-used targets:

| Command | What it does |
| --- | --- |
| `make test` | Run all unit tests |
| `make event-tests` | Run the events test suite |
| `make regression-tests` | Run the regression suite |
| `make test-file p=<path>` | Run a single test file or directory, e.g. `make test-file p=tests/unit_tests/events` |
| `make test-filter f=<regex>` | Run tests whose method name matches a regex, e.g. `make test-filter f=testItShouldFallBack` |
| `make shell` | Open a bash shell in the `api` container |
| `make exec cmd="..."` | Run an arbitrary command in the `api` container |
| `make restart-movies` | Restart the `movies` worker container |
| `make db-shell` | Open a MySQL shell against the dev database |

All targets assume the development container is running (`docker compose up`).

## License

Helioviewer.org is licensed under the Mozilla Public License.
Software libraries used by Helioviewer.org and included with this distribution may include their own licenses.
Please consult the documentation of the particular dependency for the details of it's licensing.
