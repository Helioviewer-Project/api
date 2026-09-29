<?php declare(strict_types=1);
/**
 * One-shot migration: rewrite movies.eventsState from the legacy events-tree
 * blob to the canonical { event_selections, event_visibility_selections }
 * shape. Structurally identical to the screenshots migration; only the table
 * differs (movies.id is an auto-inc integer — the public movie code is a
 * separate column, so nothing content-addressed is touched here).
 *
 *   - DRY-RUN BY DEFAULT. It only writes when passed --apply. Either way it
 *     prints "tree => event_selections & event_visibility_selections" for every
 *     row it would migrate, so the mapping can be validated before committing.
 *   - Idempotent + resumable: rows already carrying event_selections are skipped.
 *   - The primary key (auto-inc id) is never touched.
 *   - Keyset-paginated: reads in batches of --batch rows (default 10000) and
 *     writes each batch with ONE `CASE` UPDATE, so a table of N rows migrates in
 *     ~2*ceil(N/batch) queries instead of one UPDATE per row.
 *   - --start-id=N migrates only rows with id > N — resume a huge run, or skip
 *     an already-done id range.
 *   - Runs inside the maintenance window, after the new code is deployed.
 *
 * Retired in the post-deploy cleanup (with EventsStateManager).
 *
 * Usage:
 *   php management/events/migrate_movies_events_shape.php               # dry-run
 *   php management/events/migrate_movies_events_shape.php --apply       # write
 *   php management/events/migrate_movies_events_shape.php --apply --batch=5000
 *   php management/events/migrate_movies_events_shape.php --apply --start-id=1000000
 */

require_once sprintf('%s/../../vendor/autoload.php', __DIR__);
require_once sprintf('%s/../config.php', __DIR__);
require_once sprintf('%s/../../src/Database/DbConnection.php', __DIR__);
require_once sprintf('%s/events_shape.php', __DIR__);

$apply     = in_array('--apply', $argv, true);
$batchSize = events_migration_batch_size($argv); // --batch=N (default 10000)
$startId   = events_migration_start_id($argv);   // --start-id=N (migrate id > N)
$from      = $startId > 0 ? ", id > $startId" : "";
echo $apply
    ? "MODE: APPLY — writing changes to movies (batch $batchSize$from)\n\n"
    : "MODE: DRY-RUN — no writes; re-run with --apply to write (batch $batchSize$from)\n\n";

$db = new Database_DbConnection();

$migrated = 0;
$skipped  = 0;
$conversions = [];
events_migrate_table($db, HV_DB_TABLE_MOVIES, $apply, $batchSize, $startId, $migrated, $skipped, $conversions);

events_migration_print_unique($conversions, 'movies');

$verb = $apply ? "migrated" : "would migrate";
echo "movies: $verb $migrated, skipped $skipped (already new-shape)\n";
