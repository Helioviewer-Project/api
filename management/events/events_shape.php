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
