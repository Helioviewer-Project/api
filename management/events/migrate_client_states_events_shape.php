<?php declare(strict_types=1);
/**
 * One-shot migration: rewrite client_states.state, replacing the legacy
 * `eventLayers` tree with top-level `event_selections` +
 * `event_visibility_selections`, and dropping `eventLayers`.
 *
 * GOTCHA — the primary key is the SHA-256 of the state JSON. Shared-URL
 * bookmarks (?state=<hash>) resolve against it, so the id MUST stay
 * byte-identical. This uses ClientState::update() (UPDATE at a fixed id),
 * never upsert() (which re-hashes and would orphan every existing URL).
 * Post-deploy saves still go through upsert(), correctly hashing new-shape JSON.
 *
 *   - DRY-RUN BY DEFAULT. It only writes when passed --apply. Either way it
 *     prints "eventLayers => event_selections & event_visibility_selections"
 *     for every row it would migrate, so the mapping can be validated first.
 *   - Idempotent + resumable: rows already carrying event_selections are skipped.
 *   - Runs inside the maintenance window, after the new code + schema are deployed.
 *
 * Retired in the post-deploy cleanup (with EventsStateManager).
 *
 * Usage:
 *   php management/events/migrate_client_states_events_shape.php            # dry-run
 *   php management/events/migrate_client_states_events_shape.php --apply    # write
 */

require_once sprintf('%s/../../vendor/autoload.php', __DIR__);
require_once sprintf('%s/../config.php', __DIR__);
require_once sprintf('%s/../../src/Database/ClientState.php', __DIR__);
require_once sprintf('%s/events_shape.php', __DIR__);

$apply = in_array('--apply', $argv, true);
echo $apply
    ? "MODE: APPLY — writing changes to client_states\n\n"
    : "MODE: DRY-RUN — no writes; re-run with --apply to write\n\n";

$client_state = new ClientState();
$rows = $client_state->all(1000000);

$migrated = 0;
$skipped  = 0;
$conversions = [];
foreach ($rows as $row) {
    $id    = $row['id'];                          // SHA-256 hex — MUST stay the same
    $state = json_decode($row['state'], true) ?? [];

    // Already new-shape — leave it alone (idempotency / resume).
    if (isset($state['event_selections'])) {
        $skipped++;
        continue;
    }

    $tree  = (isset($state['eventLayers']) && is_array($state['eventLayers']))
        ? $state['eventLayers']
        : [];
    $shape = events_shape_from_tree($tree);
    events_migration_collect($conversions, $id, $tree, $shape);

    $state['event_selections']            = $shape['event_selections'];
    $state['event_visibility_selections'] = $shape['event_visibility_selections'];
    unset($state['eventLayers']);                 // deployed code + schema no longer carry it

    if ($apply) {
        // UPDATE at the fixed id — preserves the shared-URL hash.
        $client_state->update($id, $state);
    }
    $migrated++;
}

events_migration_print_unique($conversions, 'client_states');

$verb = $apply ? "migrated" : "would migrate";
echo "client_states: $verb $migrated, skipped $skipped (already new-shape)\n";
