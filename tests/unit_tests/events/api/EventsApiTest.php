<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\ClientInterface;
use Helioviewer\Api\Event\Api\EventsApi;
use Helioviewer\Api\Sentry\ClientInterface as SentryClientInterface;

final class EventsApiTest extends TestCase
{
    private $mockClient;
    private $mockSentry;

    protected function setUp(): void
    {
        $this->mockClient = $this->createMock(ClientInterface::class);
        $this->mockSentry = $this->createMock(SentryClientInterface::class);
    }

    public function testConstructorSetsDefaultSentryContext(): void
    {
        $this->mockSentry->expects($this->once())
            ->method('setContext')
            ->with('EventsApi', $this->callback(function ($params) {
                return array_key_exists('api_url', $params)
                    && array_key_exists('timeout', $params)
                    && array_key_exists('connect_timeout', $params);
            }));

        new EventsApi($this->mockClient, $this->mockSentry);
    }
}
