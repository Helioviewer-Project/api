<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\EventContext;
use Helioviewer\Api\Event\Api\EventsApiInterface;
use Helioviewer\Api\Event\Api\EventsApiException;
use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class EventContextTest extends TestCase
{
    private $mockApi;
    private $mockSentry;

    protected function setUp(): void
    {
        $this->mockApi    = $this->createMock(EventsApiInterface::class);
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
        // Swap the static Sentry facade's client for our mock so we can assert
        // on capture/message/setContext calls.
        Sentry::init(['enabled' => true, 'client' => $this->mockSentry]);
    }

    public function testEmptyTimestampsShortCircuitsWithNoApiCall(): void
    {
        $this->mockApi->expects($this->never())->method('getEventsForFramesWithSelections');
        $this->mockSentry->expects($this->never())->method('capture');
        $this->mockSentry->expects($this->never())->method('message');

        $context = EventContext::build([], ['HEK>>Active Region>>SPoCA'], [], $this->mockApi);

        $this->assertFalse($context->hasEvents());
        // Asking for any date on an empty context is silent (eventsByDate is empty,
        // so missing-key is expected, not a programming bug).
        $this->assertSame([], $context->getEventsForDate('2024-01-01T00:00:00.000Z'));
    }

    public function testEmptySelectionsShortCircuitsAndPopulatesRequestedTimestamps(): void
    {
        $this->mockApi->expects($this->never())->method('getEventsForFramesWithSelections');
        $this->mockSentry->expects($this->never())->method('message');

        $context = EventContext::build(['2024-01-01T00:00:00.000Z'], [], [], $this->mockApi);

        $this->assertFalse($context->hasEvents());
        // Date IS in the map (filled via array_fill_keys) -> returns [] silently.
        $this->assertSame([], $context->getEventsForDate('2024-01-01T00:00:00.000Z'));
    }

    public function testHttpFailureIsCapturedAndYieldsEmptyContext(): void
    {
        $this->mockApi->method('getEventsForFramesWithSelections')
            ->willThrowException(new EventsApiException('boom'));
        $this->mockSentry->expects($this->once())->method('capture');

        $context = EventContext::build(
            ['2024-01-01T00:00:00.000Z'],
            ['HEK>>Active Region>>SPoCA'],
            [],
            $this->mockApi,
        );

        $this->assertFalse($context->hasEvents());
        // Requested timestamps still get populated as empty lists in the failure path.
        $this->assertSame([], $context->getEventsForDate('2024-01-01T00:00:00.000Z'));
    }

    public function testHappyPathShiftsFootprintByRotationDelta(): void
    {
        $eventId = 'event-uuid';
        $ts      = '2024-01-01T00:00:00.000Z';
        $this->mockApi->method('getEventsForFramesWithSelections')->willReturn([
            'events' => [
                $eventId => [
                    'label'     => 'AR 13700',
                    'type'      => 'AR',
                    'pin'       => 'AR',
                    'path'      => 'HEK>>Active Region>>SPoCA',
                    'hv_hpc_x'  => 100.0,
                    'hv_hpc_y'  => 200.0,
                    'footprint' => [['x' => 110.0, 'y' => 210.0]],
                ],
            ],
            'timestamps' => [
                $ts => [
                    $eventId => ['hv_hpc_x' => 120.0, 'hv_hpc_y' => 230.0],
                ],
            ],
        ]);

        $context = EventContext::build([$ts], ['HEK>>Active Region>>SPoCA'], [], $this->mockApi);

        $events = $context->getEventsForDate($ts);
        $this->assertCount(1, $events);
        // dx = 120 - 100 = 20, dy = 230 - 200 = 30. Footprint point (110, 210) -> (130, 240).
        $this->assertSame(130.0, $events[0]['footprint'][0]['x']);
        $this->assertSame(240.0, $events[0]['footprint'][0]['y']);
        $this->assertSame(120.0, $events[0]['hv_hpc_x']);
        $this->assertSame(230.0, $events[0]['hv_hpc_y']);
        $this->assertTrue($context->hasEvents());
    }

    public function testHiddenLabelIsEncodedAsEmptyString(): void
    {
        $eventId = 'event-uuid';
        $ts      = '2024-01-01T00:00:00.000Z';
        $this->mockApi->method('getEventsForFramesWithSelections')->willReturn([
            'events' => [
                $eventId => [
                    'label'    => 'AR 13700',
                    'type'     => 'AR',
                    'pin'      => 'AR',
                    'path'     => 'HEK>>Active Region>>SPoCA',
                    'hv_hpc_x' => 0.0,
                    'hv_hpc_y' => 0.0,
                ],
            ],
            'timestamps' => [
                $ts => [
                    $eventId => ['hv_hpc_x' => 0.0, 'hv_hpc_y' => 0.0],
                ],
            ],
        ]);

        $context = EventContext::build(
            [$ts],
            ['HEK>>Active Region>>SPoCA'],
            ['HEK' => ['label_visibility' => false]],
            $this->mockApi,
        );

        $events = $context->getEventsForDate($ts);
        $this->assertSame('', $events[0]['label']);
    }

    public function testVisibleLabelDefaultsToTrueWhenSourceMissingFromVisibilityMap(): void
    {
        $eventId = 'event-uuid';
        $ts      = '2024-01-01T00:00:00.000Z';
        $this->mockApi->method('getEventsForFramesWithSelections')->willReturn([
            'events' => [
                $eventId => [
                    'label'    => 'AR 13700',
                    'type'     => 'AR',
                    'pin'      => 'AR',
                    'path'     => 'HEK>>Active Region>>SPoCA',
                    'hv_hpc_x' => 0.0,
                    'hv_hpc_y' => 0.0,
                ],
            ],
            'timestamps' => [
                $ts => [$eventId => ['hv_hpc_x' => 0.0, 'hv_hpc_y' => 0.0]],
            ],
        ]);

        // Visibility map empty -> default to true -> label preserved.
        $context = EventContext::build([$ts], ['HEK>>Active Region>>SPoCA'], [], $this->mockApi);

        $events = $context->getEventsForDate($ts);
        $this->assertSame('AR 13700', $events[0]['label']);
    }

    public function testGetEventsForDateOnUnknownDateLogsToSentryWhenContextNonEmpty(): void
    {
        $this->mockApi->method('getEventsForFramesWithSelections')->willReturn([
            'events'     => [],
            'timestamps' => ['2024-01-01T00:00:00.000Z' => []],
        ]);
        $this->mockSentry->expects($this->once())
            ->method('setContext')
            ->with('EventContext', $this->callback(function ($params) {
                return array_key_exists('requested_date', $params)
                    && array_key_exists('available_dates', $params)
                    && array_key_exists('events_by_date', $params);
            }));
        $this->mockSentry->expects($this->once())->method('message');

        $context = EventContext::build(
            ['2024-01-01T00:00:00.000Z'],
            ['HEK>>Active Region>>SPoCA'],
            [],
            $this->mockApi,
        );

        $this->assertSame([], $context->getEventsForDate('2099-12-31T00:00:00.000Z'));
    }

    public function testHasEventsReturnsTrueWhenAtLeastOneDateHasEvents(): void
    {
        $eventId = 'event-uuid';
        $ts1     = '2024-01-01T00:00:00.000Z';
        $ts2     = '2024-01-02T00:00:00.000Z';
        $this->mockApi->method('getEventsForFramesWithSelections')->willReturn([
            'events' => [
                $eventId => [
                    'label'    => 'AR 13700',
                    'type'     => 'AR',
                    'pin'      => 'AR',
                    'path'     => 'HEK>>Active Region>>SPoCA',
                    'hv_hpc_x' => 0.0,
                    'hv_hpc_y' => 0.0,
                ],
            ],
            'timestamps' => [
                $ts1 => [$eventId => ['hv_hpc_x' => 0.0, 'hv_hpc_y' => 0.0]],
                $ts2 => [],
            ],
        ]);

        $context = EventContext::build([$ts1, $ts2], ['HEK>>Active Region>>SPoCA'], [], $this->mockApi);

        $this->assertTrue($context->hasEvents());
    }

    public function testEmptySingletonReturnsSameInstance(): void
    {
        $this->assertSame(EventContext::empty(), EventContext::empty());
    }

    public function testEmptySingletonHasNoEventsAndIsSilentOnAnyDate(): void
    {
        $this->mockSentry->expects($this->never())->method('message');

        $empty = EventContext::empty();
        $this->assertFalse($empty->hasEvents());
        $this->assertSame([], $empty->getEventsForDate('2099-12-31T00:00:00.000Z'));
    }
}
