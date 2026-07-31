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
 *   - Runs inside the maintenance window, after the new code is deployed.
 *
 * Retired in the post-deploy cleanup (with EventsStateManager).
 *
 * Usage:
 *   php management/events/migrate_movies_events_shape.php            # dry-run
 *   php management/events/migrate_movies_events_shape.php --apply    # write
 */

require_once sprintf('%s/../../vendor/autoload.php', __DIR__);
require_once sprintf('%s/../config.php', __DIR__);
require_once sprintf('%s/../../src/Database/DbConnection.php', __DIR__);
require_once sprintf('%s/events_shape.php', __DIR__);

$apply = in_array('--apply', $argv, true);
echo $apply
    ? "MODE: APPLY — writing changes to movies\n\n"
    : "MODE: DRY-RUN — no writes; re-run with --apply to write\n\n";

$db  = new Database_DbConnection();
$res = $db->query("SELECT id, eventsState FROM movies");

$migrated = 0;
$skipped  = 0;
$conversions = [];
while ($row = $res->fetch_assoc()) {
    $tree = json_decode($row['eventsState'] ?? '', true);
    if (!is_array($tree)) {
        $tree = [];
    }

    // Already new-shape — leave it alone (idempotency / resume).
    if (isset($tree['event_selections'])) {
        $skipped++;
        continue;
    }

    $shape = events_shape_from_tree($tree);
    events_migration_collect($conversions, "#{$row['id']}", $tree, $shape);

    if ($apply) {
        $new_blob = json_encode($shape);
        $stmt = $db->link->prepare("UPDATE movies SET eventsState = ? WHERE id = ?");
        $stmt->bind_param('si', $new_blob, $row['id']);
        $stmt->execute();
        $stmt->close();
    }
    $migrated++;
}
$res->close();

events_migration_print_unique($conversions, 'movies');

$verb = $apply ? "migrated" : "would migrate";
echo "movies: $verb $migrated, skipped $skipped (already new-shape)\n";
