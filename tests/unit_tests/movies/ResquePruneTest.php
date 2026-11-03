<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

/**
 * scripts/resque_prune.php: the registrations of another host's workers and
 * of this host's dead workers go, a live worker of this host stays.
 */
final class ResquePruneTest extends TestCase
{
    private \Redisent $redis;
    private array $added = [];
    /** @var resource|null a process whose command line says resque, as a worker's does: php-resque's liveness test */
    private $probe = null;

    protected function setUp(): void
    {
        require_once HV_ROOT_DIR . '/../lib/Redisent/Redisent.php';
        $this->redis = new \Redisent(HV_REDIS_HOST, HV_REDIS_PORT);
    }

    protected function tearDown(): void
    {
        if ($this->probe !== null) {
            proc_terminate($this->probe, 9);
            proc_close($this->probe);
        }
        foreach ($this->added as $id) {
            $this->redis->srem('resque:workers', $id);
            $this->redis->del("resque:worker:$id:started");
        }
    }

    private function register(string $id): void
    {
        $this->redis->sadd('resque:workers', $id);
        $this->redis->set("resque:worker:$id:started", 'now');
        $this->added[] = $id;
    }

    private function prune(): string
    {
        $env = sprintf('QUEUE=api_movie_piece,api_movie REDIS_BACKEND=%s:%d', HV_REDIS_HOST, HV_REDIS_PORT);
        return (string) shell_exec("$env php " . escapeshellarg(HV_ROOT_DIR . '/../scripts/resque_prune.php') . ' 2>&1');
    }

    public function testOtherHostsAndDeadWorkersGoLiveOnesStay(): void
    {
        // php-resque counts a process as a live worker when its command line
        // contains "resque", as a worker's (php .../resque.php) does.
        $this->probe = proc_open(['php', '-r', 'sleep(30);', 'resque-probe'], [], $pipes);
        $pid  = proc_get_status($this->probe)['pid'];
        $host = gethostname();
        $live = "$host:$pid:api_movie_piece,api_movie";                 // a running "resque" process
        $dead = "$host:999999:api_movie_piece,api_movie";                // this host, no such process
        $gone = 'old-container:12:api_movie_piece,api_movie';           // another host
        foreach ([$live, $dead, $gone] as $id) {
            $this->register($id);
        }

        $out = $this->prune();

        $workers = $this->redis->smembers('resque:workers');
        $this->assertContains($live, $workers, $out);
        $this->assertNotContains($dead, $workers, $out);
        $this->assertNotContains($gone, $workers, $out);
        $this->assertSame(0, (int) $this->redis->exists("resque:worker:$gone:started"));
        $this->assertSame(1, (int) $this->redis->exists("resque:worker:$live:started"));
        // At least the two names above: the registry may hold the names of a
        // movies container of another host too (a dev box, or CI, where the
        // container is stopped before the tests), and those go as well.
        $this->assertMatchesRegularExpression('/^Pruned (\d+) dead worker registration\(s\); \d+ remain\.$/m', $out);
        preg_match('/^Pruned (\d+) dead/m', $out, $m);
        $this->assertGreaterThanOrEqual(2, (int) $m[1], $out);
    }

    public function testNothingToPruneIsNotAnError(): void
    {
        $this->prune();                                                   // whatever was there is gone now
        $out = $this->prune();
        $this->assertStringContainsString('Pruned 0 dead', $out);
    }
}
