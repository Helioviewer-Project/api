<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use Helioviewer\Api\Event\EventTree;
use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class EventTreeTest extends TestCase
{
    /** @var SentryClientInterface&MockObject */
    private $sentry;

    protected function setUp(): void
    {
        $this->sentry = $this->createMock(SentryClientInterface::class);
    }

    public function testItPreseedsBucketsForRequestedSources(): void
    {
        $this->sentry->expects($this->never())->method('message');
        $this->sentry->expects($this->never())->method('setContext');

        $payload = EventTree::make([], ['HEK'], $this->sentry)->export();

        $this->assertArrayHasKey('HEK>>Active Region', $payload);
        $this->assertArrayHasKey('HEK>>Flare',         $payload);
        $this->assertSame([], $payload['HEK>>Active Region']);

        // CCMC/RHESSI weren't requested -> no buckets for them.
        $this->assertArrayNotHasKey('CCMC>>DONKI',          $payload);
        $this->assertArrayNotHasKey('RHESSI>>Solar Flares', $payload);
    }

    public function testItSkipsUnknownSourcesSilently(): void
    {
        $this->sentry->expects($this->never())->method('message');
        $this->sentry->expects($this->never())->method('setContext');

        $payload = EventTree::make([], ['NONEXISTENT'], $this->sentry)->export();

        $this->assertSame([], $payload);
    }

    public function testItBucketsEventByPathPrefix(): void
    {
        $this->sentry->expects($this->never())->method('message');

        $event   = ['path' => 'HEK>>Active Region>>SPoCA', 'id' => 'a1'];
        $payload = EventTree::make([$event], ['HEK'], $this->sentry)->export();

        $this->assertSame([$event], $payload['HEK>>Active Region']);
        $this->assertSame([],       $payload['HEK>>CME']);
        $this->assertSame([],       $payload['HEK>>Flare']);
    }

    public function testItRoutesMultipleEventsToCorrectBuckets(): void
    {
        $this->sentry->expects($this->never())->method('message');

        $events = [
            ['path' => 'HEK>>Active Region>>SPoCA',  'id' => 'ar1'],
            ['path' => 'HEK>>Active Region>>NOAA',   'id' => 'ar2'],
            ['path' => 'HEK>>Flare>>SSW_Flare',      'id' => 'fl1'],
            ['path' => 'CCMC>>DONKI>>CME',           'id' => 'cme1'],
        ];

        $payload = EventTree::make($events, ['HEK', 'CCMC'], $this->sentry)->export();

        $this->assertCount(2, $payload['HEK>>Active Region']);
        $this->assertCount(1, $payload['HEK>>Flare']);
        $this->assertCount(1, $payload['CCMC>>DONKI']);
        $this->assertSame([], $payload['CCMC>>Solar Flare Predictions']);
    }

    public function testItCreatesDynamicBucketAndReportsUnknownPath(): void
    {
        $this->sentry->expects($this->once())
            ->method('setContext')
            ->with(
                $this->equalTo('SimpleTreeUnknownPaths'),
                $this->callback(function (array $params): bool {
                    return $params['unknown_paths']     === ['HEK>>NewConcept>>SomeFRM']
                        && $params['invalid_paths']     === []
                        && $params['requested_sources'] === ['HEK'];
                })
            );

        $this->sentry->expects($this->once())
            ->method('message')
            ->with($this->equalTo(
                'simpletree: encountered paths not in EventSelections::$event_types_map'
            ));

        $event   = ['path' => 'HEK>>NewConcept>>SomeFRM', 'id' => 'x'];
        $payload = EventTree::make([$event], ['HEK'], $this->sentry)->export();

        $this->assertArrayHasKey('HEK>>NewConcept', $payload);
        $this->assertSame([$event], $payload['HEK>>NewConcept']);
    }

    public function testItReportsOnlyTheFirstOccurrenceOfEachNewConcept(): void
    {
        // Once a dynamic bucket is created (HEK>>NewConcept from event 1),
        // subsequent events whose path falls under that bucket are matched
        // by the regular str_starts_with loop and never re-reported. So
        // unknown_paths captures one path per *new concept*, not per event.
        $this->sentry->expects($this->once())
            ->method('setContext')
            ->with(
                'SimpleTreeUnknownPaths',
                $this->callback(function (array $params): bool {
                    $reported = $params['unknown_paths'];
                    return count($reported) === 2
                        && in_array('HEK>>NewConcept>>FrmA', $reported, true)
                        && in_array('HEK>>AnotherNew>>FrmA', $reported, true);
                })
            );

        $this->sentry->expects($this->once())->method('message');

        $events = [
            ['path' => 'HEK>>NewConcept>>FrmA'], // 1st: bucket created, recorded
            ['path' => 'HEK>>NewConcept>>FrmA'], // 2nd: matched silently
            ['path' => 'HEK>>NewConcept>>FrmB'], // 3rd: matched silently
            ['path' => 'HEK>>AnotherNew>>FrmA'], // 4th: new concept, recorded
        ];

        EventTree::make($events, ['HEK'], $this->sentry);
    }

    public function testItDropsMalformedPathsAndReportsThem(): void
    {
        $this->sentry->expects($this->once())
            ->method('setContext')
            ->with(
                'SimpleTreeUnknownPaths',
                $this->callback(function (array $params): bool {
                    $invalid = $params['invalid_paths'];
                    return $params['unknown_paths'] === []
                        && in_array('',                $invalid, true)
                        && in_array('HEK',             $invalid, true)
                        && in_array('>>',              $invalid, true)
                        && in_array('HEK>>',           $invalid, true)
                        && in_array('>>Active Region', $invalid, true);
                })
            );

        $this->sentry->expects($this->once())->method('message');

        $events = [
            ['path' => ''],                  // empty
            ['path' => 'HEK'],               // single segment
            ['path' => '>>'],                // both segments empty
            ['path' => 'HEK>>'],             // second empty
            ['path' => '>>Active Region'],   // first empty
            [],                              // missing 'path' key (treated as '')
        ];

        $payload = EventTree::make($events, ['HEK'], $this->sentry)->export();

        // None of the malformed events should reach any bucket.
        foreach ($payload as $bucket => $items) {
            $this->assertSame([], $items, "Bucket $bucket should have stayed empty");
        }
    }

    public function testItStaysSilentWhenEverythingMatches(): void
    {
        $this->sentry->expects($this->never())->method('message');
        $this->sentry->expects($this->never())->method('setContext');

        $events = [
            ['path' => 'HEK>>Active Region>>SPoCA'],
            ['path' => 'HEK>>Flare>>SSW_Flare'],
            ['path' => 'CCMC>>DONKI>>CME'],
        ];

        EventTree::make($events, ['HEK', 'CCMC'], $this->sentry);
    }

    public function testItReportsBothUnknownAndInvalidPathsInSameRequest(): void
    {
        $this->sentry->expects($this->once())
            ->method('setContext')
            ->with(
                'SimpleTreeUnknownPaths',
                $this->callback(function (array $params): bool {
                    return $params['unknown_paths'] === ['HEK>>NewConcept>>X']
                        && $params['invalid_paths'] === [''];
                })
            );

        $this->sentry->expects($this->once())->method('message');

        $events = [
            ['path' => 'HEK>>NewConcept>>X'],  // unknown
            ['path' => ''],                    // invalid
        ];

        EventTree::make($events, ['HEK'], $this->sentry);
    }

    public function testItFallsBackToStaticSentryClientWhenNoneInjected(): void
    {
        $this->sentry->expects($this->once())->method('message');
        $this->sentry->expects($this->once())->method('setContext');

        // Bind the mock as Sentry::$client; make() with no 3rd arg should pick it up.
        Sentry::init(['enabled' => true, 'client' => $this->sentry]);

        EventTree::make([['path' => 'HEK>>NewConcept>>X']], ['HEK']);
    }
}
