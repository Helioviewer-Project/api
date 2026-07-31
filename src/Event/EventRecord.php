<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * Value object wrapping the raw event dict returned by the upstream events API.
 *
 * Keeps the raw wire-shape as-is (so we don't materialize new arrays for every
 * field) and hangs small parsing helpers on the object side. Callers can pull
 * fields with get('key') or reach specific typed helpers like
 * getSourceFromPath().
 */
class EventRecord
{
    public function __construct(private array $data)
    {
    }

    /**
     * Extract the leading SOURCE token from the event's canonical path, e.g.
     * "HEK>>Active Region>>SPoCA" -> "HEK". Missing / empty path -> ''.
     */
    public function getSourceFromPath(): string
    {
        return explode('>>', $this->data['path'] ?? '', 2)[0];
    }

    /**
     * Generic accessor for raw fields. Returns $default when the key is missing.
     */
    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }
}
