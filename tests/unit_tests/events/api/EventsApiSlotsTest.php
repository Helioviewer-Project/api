<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Event\Api\EventsApiSlots;

/**
 * EventsApiSlots: the box's cap on events requests in flight, held in Redis
 * and shared by every process. Each test uses its own key prefix, so the
 * live workers' slots are never touched.
 */
final class EventsApiSlotsTest extends TestCase
{
    private string $key;

    protected function setUp(): void
    {
        $this->key = 'helioviewer:test:slot:' . bin2hex(random_bytes(4)) . ':';
    }

    public function testOnlyLimitSlotsExistAcrossProcesses(): void
    {
        $a = new EventsApiSlots(2, 30, $this->key);
        $b = new EventsApiSlots(2, 30, $this->key);      // another worker

        $this->assertNotNull($a->take());
        $this->assertNotNull($b->take());
        $this->assertNull($a->tryTake(), 'two slots, both taken');
        $this->assertNull($b->tryTake());

        $a->releaseAll();
        $this->assertNotNull($b->tryTake(), 'a released slot is free for anyone');
        $this->assertCount(2, $b->held());
        $b->releaseAll();
        $this->assertSame([], $b->held());
    }

    public function testTakeWaitsForAFreeSlot(): void
    {
        $a = new EventsApiSlots(1, 30, $this->key);
        $b = new EventsApiSlots(1, 1, $this->key);      // the holder's slot lives one second
        $this->assertNotNull($b->take());

        $start = microtime(true);
        $slot = $a->take();                              // waits until the holder's slot expires
        $this->assertNotNull($slot);
        $this->assertGreaterThan(0.5, microtime(true) - $start);
        $a->releaseAll();
    }

    public function testAnExpiredSlotTakenByAnotherIsNotReleasedByTheFirst(): void
    {
        $a = new EventsApiSlots(1, 1, $this->key);
        $b = new EventsApiSlots(1, 30, $this->key);
        $this->assertSame(0, $a->take());
        usleep(1200000);                                 // a's slot expired
        $this->assertSame(0, $b->take(), 'b holds the slot now');
        $a->release(0);                                  // a's token no longer matches
        $this->assertNull($b->tryTake(), 'b still holds the only slot');
        $this->assertNull((new EventsApiSlots(1, 30, $this->key))->tryTake());
        $b->releaseAll();
    }

    public function testFromConfigFollowsTheKey(): void
    {
        $slots = EventsApiSlots::fromConfig();
        $limit = defined('HV_EVENTS_API_MAX_IN_FLIGHT') ? (int) HV_EVENTS_API_MAX_IN_FLIGHT : 0;
        if ($limit < 1) {
            $this->assertNull($slots);
        } else {
            $this->assertSame($limit, $slots->limit());
        }
    }
}
