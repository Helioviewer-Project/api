<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Event\Api\EventsApiException;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

/**
 * getEventsForFramesWithSelections with concurrency > 1: chunk requests go
 * through a Guzzle pool. A fake handler answers each chunk with events built
 * from the timestamps it was sent, holds the replies back until the pool
 * waits, and then delivers them last-chunk-first, so the tests also cover
 * replies arriving out of order.
 */
final class GetEventsForFramesWithSelectionsParallelTest extends TestCase
{
    private $mockSentry;

    /** @var array<int, array> request bodies, in the order they were sent */
    private array $sent = [];

    /** @var int most requests in flight at the same time */
    private int $maxInFlight = 0;

    /** @var array<int, array{0: Promise, 1: callable}> replies not delivered yet */
    private array $pending = [];

    /** @var array<int, true> chunk numbers (1-based, in send order) that fail */
    private array $failing = [];

    protected function setUp(): void
    {
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
    }

    private function api(int $concurrency): EventsApi
    {
        $handler = function (RequestInterface $request) {
            $body = json_decode((string) $request->getBody(), true);
            $this->sent[] = $body;
            $n = count($this->sent);
            $promise = new Promise(function () {
                // Deliver every held reply, newest first.
                while ($this->pending) {
                    [$p, $settle] = array_pop($this->pending);
                    $settle($p);
                }
            });
            $this->pending[] = [$promise, function (Promise $p) use ($body, $n, $request) {
                if (isset($this->failing[$n])) {
                    $p->reject(new \GuzzleHttp\Exception\ConnectException("chunk $n failed", $request));
                    return;
                }
                $events = [];
                $timestamps = [];
                foreach ($body['timestamps'] as $t) {
                    $timestamps[$t] = ['evt-SHARED' => ['hv_hpc_x' => $n]];
                }
                // Every chunk reports the shared event, labelled by its chunk:
                // first-wins merging must keep chunk 1's label.
                $events['evt-SHARED'] = ['label' => "from chunk $n"];
                $events["evt-$n"] = ['label' => "only in chunk $n"];
                $p->resolve(new Response(200, [], json_encode(['events' => $events, 'timestamps' => $timestamps])));
            }];
            $this->maxInFlight = max($this->maxInFlight, count($this->pending));
            return $promise;
        };
        $client = new Client(['handler' => HandlerStack::create($handler)]);
        return new EventsApi($client, $this->mockSentry, $concurrency);
    }

    private static function timestamps(int $count): array
    {
        $out = [];
        for ($i = 0; $i < $count; $i++) {
            $out[] = date('Y-m-d H:i:s', 1720728000 + 300 * $i);
        }
        return $out;
    }

    public function testItShouldSpreadTimestampsOverTheConcurrentRequests(): void
    {
        // 120 timestamps at chunk size 150, 6 at a time -> 6 chunks of 20
        $this->api(6)->getEventsForFramesWithSelections(self::timestamps(120), ['HEK>>Flare'], 150);

        $this->assertCount(6, $this->sent);
        $this->assertSame([20, 20, 20, 20, 20, 20], array_map(fn ($b) => count($b['timestamps']), $this->sent));
    }

    public function testItShouldNotMakeChunksLargerThanTheMaximum(): void
    {
        // 300 timestamps, 6 at a time would be chunks of 50 -> capped at 25 -> 12 chunks
        $this->api(6)->getEventsForFramesWithSelections(self::timestamps(300), ['HEK>>Flare'], 150);

        $this->assertCount(12, $this->sent);
        $this->assertSame(array_fill(0, 12, 25), array_map(fn ($b) => count($b['timestamps']), $this->sent));
    }

    public function testItShouldNotMakeChunksSmallerThanTheMinimum(): void
    {
        // 30 timestamps, 6 at a time would be chunks of 5 -> raised to 10 -> 3 chunks
        $this->api(6)->getEventsForFramesWithSelections(self::timestamps(30), ['HEK>>Flare'], 150);

        $this->assertSame([10, 10, 10], array_map(fn ($b) => count($b['timestamps']), $this->sent));
    }

    public function testItShouldNotExceedTheRequestedChunkSize(): void
    {
        // 100 timestamps, 2 at a time would be chunks of 50, but chunk size 20 wins -> 5 chunks
        $this->api(2)->getEventsForFramesWithSelections(self::timestamps(100), ['HEK>>Flare'], 20);

        $this->assertSame([20, 20, 20, 20, 20], array_map(fn ($b) => count($b['timestamps']), $this->sent));
    }

    public function testItShouldKeepAtMostConcurrencyRequestsInFlight(): void
    {
        $this->api(3)->getEventsForFramesWithSelections(self::timestamps(100), ['HEK>>Flare'], 10);

        $this->assertCount(10, $this->sent);
        $this->assertSame(3, $this->maxInFlight);
    }

    public function testItShouldMergeInChunkOrderWhateverOrderRepliesArrive(): void
    {
        $timestamps = self::timestamps(40);
        $merged = $this->api(4)->getEventsForFramesWithSelections($timestamps, ['HEK>>Flare', 'CCMC'], 150);

        // the same selections go with every chunk, and every timestamp is asked once
        $this->assertCount(4, $this->sent);
        foreach ($this->sent as $body) {
            $this->assertSame(['HEK>>Flare', 'CCMC'], $body['selections']);
        }
        $this->assertSame($timestamps, array_merge(...array_map(fn ($b) => $b['timestamps'], $this->sent)));

        // replies were delivered last chunk first, but the merge is in chunk
        // order: the shared event keeps chunk 1's copy, and timestamps keep
        // their original order
        $this->assertSame('from chunk 1', $merged['events']['evt-SHARED']['label']);
        $this->assertSame(['evt-SHARED', 'evt-1', 'evt-2', 'evt-3', 'evt-4'], array_keys($merged['events']));
        $this->assertSame($timestamps, array_keys($merged['timestamps']));
        $this->assertSame(1, $merged['timestamps'][$timestamps[0]]['evt-SHARED']['hv_hpc_x']);
        $this->assertSame(4, $merged['timestamps'][$timestamps[39]]['evt-SHARED']['hv_hpc_x']);
    }

    public function testItShouldMatchTheSequentialResult(): void
    {
        $timestamps = self::timestamps(60);
        // concurrency 3 -> 3 chunks of 20; concurrency 1 with chunk size 20 -> the same 3 chunks
        $parallel = $this->api(3)->getEventsForFramesWithSelections($timestamps, ['HEK>>Flare'], 150);
        $this->sent = [];
        $sequential = $this->api(1)->getEventsForFramesWithSelections($timestamps, ['HEK>>Flare'], 20);

        $this->assertSame($sequential, $parallel);
    }

    public function testItShouldThrowAndCaptureSentryWhenAChunkFails(): void
    {
        $this->failing = [2 => true, 4 => true];

        $this->mockSentry->expects($this->once())
            ->method('capture')
            ->with($this->isInstanceOf(EventsApiException::class));

        // the lowest failing chunk is reported, as the sequential path would
        $this->expectException(EventsApiException::class);
        $this->expectExceptionMessage('Failed to fetch frames_with_selections: chunk 2 failed');

        $this->api(4)->getEventsForFramesWithSelections(self::timestamps(40), ['HEK>>Flare'], 150);
    }

    public function testItShouldThrowWhenAChunkReplyIsNotJson(): void
    {
        $handler = fn () => new \GuzzleHttp\Promise\FulfilledPromise(new Response(200, [], 'not json'));
        $api = new EventsApi(new Client(['handler' => HandlerStack::create($handler)]), $this->mockSentry, 4);

        $this->expectException(EventsApiException::class);

        $api->getEventsForFramesWithSelections(self::timestamps(40), ['HEK>>Flare'], 150);
    }

    public function testItShouldSendASingleChunkWithoutThePool(): void
    {
        // 8 timestamps -> one chunk, so nothing to overlap: one request()
        $client = $this->createMock(\GuzzleHttp\ClientInterface::class);
        $client->expects($this->never())->method('requestAsync');
        $client->expects($this->once())->method('request')
            ->willReturn(new Response(200, [], json_encode(['events' => [], 'timestamps' => []])));

        (new EventsApi($client, $this->mockSentry, 6))
            ->getEventsForFramesWithSelections(self::timestamps(8), ['HEK>>Flare'], 150);
    }
}
