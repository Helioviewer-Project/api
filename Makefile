DOCKER_EXEC = docker compose exec api
PHPUNIT = $(DOCKER_EXEC) vendor/bin/phpunit --bootstrap tests/autoload.php --testdox

# Events-shape migration runners. Dry-run by default; pass APPLY=1 to write.
MIGRATE_EVENTS = $(DOCKER_EXEC) php management/events
APPLY_FLAG = $(if $(APPLY),--apply,)

.PHONY: help test event-tests regression-tests test-filter test-file shell exec restart-movies db-shell \
        migrate-events migrate-screenshots migrate-movies migrate-client-states requeue-movie

.DEFAULT_GOAL := help

help:
	@echo "Available commands:"
	@echo "  make test                  - Run all unit tests"
	@echo "  make event-tests           - Run EventsApi tests"
	@echo "  make regression-tests      - Run regression tests"
	@echo "  make test-filter f=...     - Run tests whose name matches the PHPUnit --filter regex"
	@echo "                               e.g. make test-filter f=testItShouldFallBack"
	@echo "                               e.g. make test-filter f=LegacyEventString"
	@echo "  make test-file p=...       - Run a single test file or directory under tests/unit_tests"
	@echo "                               e.g. make test-file p=tests/unit_tests/validation/LegacyEventStringTest.php"
	@echo "                               e.g. make test-file p=tests/unit_tests/events"
	@echo "  make shell                 - Open bash shell in api container"
	@echo "  make exec cmd=\"...\"        - Run a command in api container"
	@echo "  make restart-movies        - Restart movies container"
	@echo "  make db-shell              - Open MySQL shell"
	@echo "  make migrate-events        - Events-shape migration, all tables (dry-run)"
	@echo "                               lists unique tree=>shape conversions, writes nothing"
	@echo "                               add APPLY=1 to actually write, e.g. make migrate-events APPLY=1"
	@echo "  make migrate-screenshots   - Only the screenshots table (dry-run; APPLY=1 to write)"
	@echo "  make migrate-movies        - Only the movies table (dry-run; APPLY=1 to write)"
	@echo "  make migrate-client-states - Only the client_states table (dry-run; APPLY=1 to write)"
	@echo "  make requeue-movie id=...  - Rebuild a movie by its public id (force=true)"
	@echo "                               e.g. make requeue-movie id=hl66n"

shell:
	$(DOCKER_EXEC) bash

exec:
	$(DOCKER_EXEC) $(cmd)

test:
	$(PHPUNIT) tests/unit_tests

event-tests:
	$(PHPUNIT) tests/unit_tests/events/

regression-tests:
	$(PHPUNIT) tests/unit_tests/regression/

# Run only tests whose method name matches the given regex.
# Usage: make test-filter f=<regex>      (also accepts: filter=, FILTER=)
test-filter:
	@if [ -z "$(f)$(filter)$(FILTER)" ]; then \
		echo "Error: pass a filter, e.g. make test-filter f=testItShouldFallBack"; exit 1; \
	fi
	$(PHPUNIT) tests/unit_tests --filter '$(f)$(filter)$(FILTER)'

# Run a single test file (or limit to a sub-directory).
# Usage: make test-file p=tests/unit_tests/validation/LegacyEventStringTest.php
test-file:
	@if [ -z "$(p)$(path)$(PATH_)" ]; then \
		echo "Error: pass a path, e.g. make test-file p=tests/unit_tests/validation/LegacyEventStringTest.php"; exit 1; \
	fi
	$(PHPUNIT) '$(p)$(path)$(PATH_)'

restart-movies:
	docker compose restart movies

db-shell:
	docker compose exec database mariadb -u helioviewer -phelioviewer helioviewer

# Events-shape migration. Each target is dry-run by default (lists unique
# tree=>shape conversions, no writes); pass APPLY=1 to perform the UPDATEs.
migrate-screenshots:
	$(MIGRATE_EVENTS)/migrate_screenshots_events_shape.php $(APPLY_FLAG)

migrate-movies:
	$(MIGRATE_EVENTS)/migrate_movies_events_shape.php $(APPLY_FLAG)

migrate-client-states:
	$(MIGRATE_EVENTS)/migrate_client_states_events_shape.php $(APPLY_FLAG)

migrate-events: migrate-screenshots migrate-movies migrate-client-states

# Rebuild a movie by its public id (e.g. hl66n). force=true so it re-queues even
# when the cached file already exists. Usage: make requeue-movie id=hl66n
requeue-movie:
	@if [ -z "$(id)" ]; then echo "Error: pass id=<movieCode>, e.g. make requeue-movie id=hl66n"; exit 1; fi
	$(DOCKER_EXEC) curl -s "http://localhost/?action=reQueueMovie&id=$(id)&force=true"
	@echo
