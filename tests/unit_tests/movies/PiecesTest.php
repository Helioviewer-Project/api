<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Movie\FastMovie;
use Helioviewer\Api\Movie\Pieces;

final class PiecesTest extends TestCase
{
    private Pieces $pieces;

    protected function setUp(): void
    {
        require_once __DIR__ . '/../../../src/Movie/FastMovie.php';
        $this->pieces = new Pieces('test-' . bin2hex(random_bytes(4)));
    }

    protected function tearDown(): void
    {
        $this->pieces->clear();
    }

    public function testANeverOpenedRecordIsAllZero(): void
    {
        $this->assertSame(['count' => 0, 'done' => 0, 'failed' => 0, 'frames' => 0, 'started' => '', 'updated' => 0], $this->pieces->progress());
        $this->assertFalse($this->pieces->report(true));
    }

    public function testTheLastReportAndOnlyTheLastIsToldSo(): void
    {
        $this->pieces->open(3, '2026-11-02 06:00:00');
        $this->pieces->frameDone();
        $this->pieces->frameDone();

        $this->assertFalse($this->pieces->report(true));
        $this->assertFalse($this->pieces->report(false));
        $p = $this->pieces->progress();
        $this->assertSame([3, 1, 1, 2, '2026-11-02 06:00:00'], [$p['count'], $p['done'], $p['failed'], $p['frames'], $p['started']]);
        $this->assertGreaterThan(time() - 60, $p['updated']);

        $this->assertTrue($this->pieces->report(true));
        // A report after the count (a retried job) completes nothing again.
        $this->assertFalse($this->pieces->report(true));
        $this->assertSame(['count' => 3, 'done' => 3, 'failed' => 1], array_slice($this->pieces->progress(), 0, 3));
    }

    public function testOpeningAgainStartsOver(): void
    {
        $this->pieces->open(2, 'a');
        $this->assertFalse($this->pieces->report(true));
        $this->assertTrue($this->pieces->report(true));

        $this->pieces->open(2, 'b');
        $this->assertSame(['count' => 2, 'done' => 0, 'failed' => 0, 'frames' => 0], array_slice($this->pieces->progress(), 0, 4));
        $this->assertFalse($this->pieces->report(true));
        $this->assertTrue($this->pieces->report(true));
    }

    public function testPieceRangesCoverTheFramesOnceInOrder(): void
    {
        $this->assertSame([[0, 4]], FastMovie::pieceRanges(5, 10, 8));
        $this->assertSame([[0, 9], [10, 19], [20, 29]], FastMovie::pieceRanges(30, 10, 8));
        $this->assertSame(8, count(FastMovie::pieceRanges(300, 10, 8)));
        $this->assertSame(30, count(FastMovie::pieceRanges(300, 10, 30)));
        $this->assertSame([[0, 0]], FastMovie::pieceRanges(1, 10, 8));

        foreach ([[300, 10, 8], [300, 10, 30], [100, 10, 8], [31, 10, 8], [12, 5, 100], [7, 1, 100]] as [$frames, $min, $max]) {
            $ranges = FastMovie::pieceRanges($frames, $min, $max);
            $this->assertLessThanOrEqual($max, count($ranges));
            $next = 0;
            foreach ($ranges as [$first, $last]) {
                $this->assertSame($next, $first);
                $this->assertGreaterThanOrEqual($first, $last);
                $this->assertGreaterThanOrEqual($min, $last - $first + 1);
                $next = $last + 1;
            }
            $this->assertSame($frames, $next);
            // the pieces differ by at most one frame
            $sizes = array_map(fn($r) => $r[1] - $r[0] + 1, $ranges);
            $this->assertLessThanOrEqual(1, max($sizes) - min($sizes));
        }
    }
}
