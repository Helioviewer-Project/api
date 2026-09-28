<?php declare(strict_types=1);

namespace Helioviewer\Api\Event;

/**
 * Rasterises an SVG document to a PNG blob using rsvg-convert (librsvg).
 *
 * This is the one fragile, external-command part of event-region rendering:
 * locating the binary, running it (piped, no temp file), and turning any failure
 * into a diagnostic RuntimeException carrying rsvg's exit code and stderr. It is
 * kept out of the image compositor so it can be unit-tested and reused on its own.
 *
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */
class SvgRasterizer
{
    /** @var string|null memoized detection for the process (one check per movie run) */
    private static ?string $detected = null;
    private static bool $checked = false;

    private ?string $binary;

    /**
     * @param string|null $binary explicit path to rsvg-convert (for tests); an
     *                            unusable path reads as unavailable. Auto-detected
     *                            on PATH when null.
     */
    public function __construct(?string $binary = null)
    {
        if ($binary !== null) {
            $this->binary = @is_executable($binary) ? $binary : null;
        } else {
            $this->binary = self::detect();
        }
    }

    /** True when an rsvg-convert binary is available. */
    public function isAvailable(): bool
    {
        return $this->binary !== null;
    }

    /**
     * Rasterise $svg to a PNG blob at $width x $height.
     *
     * @throws \RuntimeException when rsvg-convert is missing, cannot be started,
     *         exits non-zero, or produces no output. The message carries the exit
     *         code and rsvg's stderr, so a broken render fails loudly and diagnosably.
     */
    public function rasterize(string $svg, int $width, int $height): string
    {
        if ($this->binary === null) {
            throw new \RuntimeException('rsvg-convert (librsvg) is required but was not found on PATH.');
        }
        $cmd = escapeshellarg($this->binary) . ' -w ' . $width . ' -h ' . $height;
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            throw new \RuntimeException('Could not start rsvg-convert (proc_open failed) — is command execution allowed?');
        }
        fwrite($pipes[0], $svg);
        fclose($pipes[0]);
        $png = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]); // capture stderr for the error message
        fclose($pipes[1]);
        fclose($pipes[2]);
        $rc = proc_close($proc);
        if ($rc !== 0 || !is_string($png) || $png === '') {
            $why = trim((string) $err);
            throw new \RuntimeException(sprintf(
                'rsvg-convert failed to rasterise the SVG (exit %d)%s',
                $rc,
                $why !== '' ? ': ' . $why : ' — produced no output'
            ));
        }
        return $png;
    }

    /** Locate rsvg-convert on PATH, memoized for the process. */
    private static function detect(): ?string
    {
        if (!self::$checked) {
            self::$checked = true;
            $path = trim((string) @shell_exec('command -v rsvg-convert 2>/dev/null'));
            self::$detected = ($path !== '' && @is_executable($path)) ? $path : null;
        }
        return self::$detected;
    }
}
