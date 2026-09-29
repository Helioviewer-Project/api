<?php declare(strict_types=1);
/**
 * Shared derivation for the events-state shape migration.
 *
 * Converts a legacy events-tree blob (top-level tree_HEK / tree_CCMC / ...)
 * into the canonical new shape consumed everywhere since the events_selections
 * payload adoption:
 *
 *     {
 *       "event_selections":            ["HEK>>Active Region", ...],
 *       "event_visibility_selections": { "HEK": {"marker_visibility":true,
 *                                                 "label_visibility":false}, ... }
 *     }
 *
 * EventsStateManager is the one-shot adapter: it already knows how to walk the
 * legacy tree and emit canonical selection paths. An empty/absent tree yields
 * empty selections and all-true visibility, so the same path covers the rows
 * whose blob is {} (no events were ever selected).
 *
 * Included by the three management/events/migrate_*_events_shape.php runners;
 * all four files retire in the post-deploy cleanup alongside EventsStateManager.
 *
 * @category Migration
 * @package  Helioviewer
 */

use Helioviewer\Api\Event\EventsStateManager;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Sentry\Sentry;

// EventsStateManager::getSelections() reports unmapped event pins to Sentry.
// These CLI migrations run outside the web bootstrap that normally initializes
// it, so ensure a (disabled, VoidClient) instance exists — an empty config
// defaults to disabled — otherwise those reports throw. No-op if the caller
// already initialized Sentry.
if (Sentry::$client === null) {
    Sentry::init([]);
}

/**
 * @param array $tree Legacy events tree (tree_HEK / tree_CCMC / ...), or [].
 * @return array{event_selections: string[], event_visibility_selections: array<string, array{marker_visibility: bool, label_visibility: bool}>}
 */
function events_shape_from_tree(array $tree): array
{
    // Canonical selection paths — the manager does the legacy-tree walk.
    $selections = EventsStateManager::buildFromEventsState($tree)->getSelections();

    // Per-source visibility. The legacy tree stores markers_visible /
    // labels_visible per tree_SOURCE; a missing flag means "on" by historical
    // convention (matches the manager's own default).
    $visibility = [];
    foreach (EventsApi::VALID_SOURCES as $source) {
        $group = $tree['tree_' . $source] ?? [];
        $visibility[$source] = [
            'marker_visibility' => (bool)($group['markers_visible'] ?? true),
            'label_visibility'  => (bool)($group['labels_visible']  ?? true),
        ];
    }

    return [
        'event_selections'            => $selections,
        'event_visibility_selections' => $visibility,
    ];
}

/**
 * Accumulate a conversion into $bucket keyed by its unique signature, so many
 * rows that share the same "tree => shape" conversion collapse into a single
 * listed entry (with a count and a sample id). $bucket is passed by reference.
 *
 * @param array  $bucket   accumulator, keyed by conversion signature
 * @param string $sampleId a human label for one row with this conversion
 * @param array  $tree     the legacy input tree (or [] when the row had none)
 * @param array  $shape    the derived new-shape blob
 */
function events_migration_collect(array &$bucket, string $sampleId, array $tree, array $shape): void
{
    $sig = json_encode([$tree, $shape]);
    if (!isset($bucket[$sig])) {
        $bucket[$sig] = ['count' => 0, 'sample' => $sampleId, 'tree' => $tree, 'shape' => $shape];
    }
    $bucket[$sig]['count']++;
}

/**
 * Print each UNIQUE conversion once — "tree => event_selections &
 * event_visibility_selections" — separated by a banner line so they are easy to
 * scan. Identical conversions are folded together with a row count.
 *
 * @param array  $bucket the accumulator filled by events_migration_collect()
 * @param string $table  table name, for the per-entry heading
 */
function events_migration_print_unique(array $bucket, string $table): void
{
    $json = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
    $sep  = str_repeat('*', 60);

    echo $sep . "\n";
    foreach ($bucket as $entry) {
        echo "{$table}: {$entry['count']} row(s), e.g. {$entry['sample']}\n";
        echo "   tree                        => " . ($entry['tree'] === [] ? "{} (no events)" : json_encode($entry['tree'], $json)) . "\n";
        echo "   event_selections            => " . json_encode($entry['shape']['event_selections'], $json) . "\n";
        echo "   event_visibility_selections => " . json_encode($entry['shape']['event_visibility_selections'], $json) . "\n";
        echo $sep . "\n";
    }
    echo count($bucket) . " unique conversion(s)\n";
}

/**
 * Read a --batch=N argument (rows per page); defaults to 10000.
 *
 * @param array $argv    the script's $argv
 * @param int   $default rows per page when --batch is absent
 * @return int  a positive batch size
 */
function events_migration_batch_size(array $argv, int $default = 10000): int
{
    foreach ($argv as $arg) {
        if (preg_match('/^--batch=(\d+)$/', $arg, $m)) {
            return max(1, (int)$m[1]);
        }
    }
    return $default;
}

/**
 * Migrate one events-state table (movies / screenshots) to the canonical shape
 * in keyset-paginated batches. Per page it runs ONE SELECT (10k rows ordered by
 * id) and, when $apply, ONE `UPDATE ... SET eventsState = CASE id ... END WHERE
 * id IN (...)` — so a table of N rows costs ~2*ceil(N/$batchSize) queries rather
 * than one UPDATE per row.
 *
 * The id primary key is never written (a CASE UPDATE can only touch existing
 * rows — it cannot insert, so the NOT NULL/no-default columns are irrelevant),
 * rows already carrying event_selections are skipped (idempotent/resumable),
 * and each page's write is wrapped in a transaction. Unique tree=>shape
 * conversions accumulate into $conversions for the dry-run report.
 *
 * @param Database_DbConnection $db
 * @param string $table       resolved table name (an HV_DB_TABLE_* constant)
 * @param bool   $apply        false = dry-run (reads + counts, writes nothing)
 * @param int    $batchSize    rows per page
 * @param int    $migrated     out (by ref): rows converted / would-convert
 * @param int    $skipped      out (by ref): rows already new-shape
 * @param array  $conversions  out (by ref): unique conversions for the report
 */
function events_migrate_table(
    Database_DbConnection $db,
    string $table,
    bool $apply,
    int $batchSize,
    int &$migrated,
    int &$skipped,
    array &$conversions
): void {
    $lastId = 0;
    do {
        // One SELECT per page — keyset on the PK (no OFFSET rescans).
        $sql  = sprintf(
            'SELECT id, eventsState FROM %s WHERE id > %d ORDER BY id LIMIT %d',
            $table, $lastId, $batchSize
        );
        $res  = $db->query($sql);
        $rows = $res->fetch_all(MYSQLI_ASSOC);
        $res->close();

        $updates = []; // id => new eventsState blob, for this page's single UPDATE
        foreach ($rows as $row) {
            $lastId = (int)$row['id']; // advance keyset by the max id examined

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
            $updates[(int)$row['id']] = json_encode($shape);
            $migrated++;
        }

        if ($apply && $updates) {
            events_apply_eventsstate_batch($db, $table, $updates);
        }
    } while (count($rows) === $batchSize);
}

/**
 * Write one page of {id => eventsState} back with a single CASE UPDATE, in a
 * transaction. Values are escaped (ids cast to int, blobs real_escape_string'd);
 * the table name is a trusted HV_DB_TABLE_* constant, never user input.
 *
 * @param Database_DbConnection $db
 * @param string $table
 * @param array<int,string> $updates id => eventsState JSON blob
 */
function events_apply_eventsstate_batch(Database_DbConnection $db, string $table, array $updates): void
{
    $case = '';
    foreach ($updates as $id => $blob) {
        $case .= sprintf(" WHEN %d THEN '%s'", (int)$id, $db->link->real_escape_string($blob));
    }
    $ids = implode(',', array_map('intval', array_keys($updates)));
    $sql = sprintf('UPDATE %s SET eventsState = CASE id%s END WHERE id IN (%s)', $table, $case, $ids);

    $db->link->begin_transaction();
    try {
        $db->query($sql);
        $db->link->commit();
    } catch (\Throwable $e) {
        $db->link->rollback();
        throw $e;
    }
}
