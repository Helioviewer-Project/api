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

    public static function filterSourcesProvider(): array
    {
        return [
            'all_valid' => [
                ['HEK', 'CCMC', 'RHESSI'],
                ['HEK', 'CCMC', 'RHESSI'],
            ],
            'mixed_valid_and_invalid' => [
                ['HEK', 'FOO', 'BAR'],
                ['HEK'],
            ],
            'tree_prefixed_rejected' => [
                ['tree_HEK', 'tree_CCMC'],
                [],
            ],
            'empty_input' => [
                [],
                [],
            ],
            'all_invalid' => [
                ['FOO', 'BAR', 'BAZ'],
                [],
            ],
            'single_valid' => [
                ['CCMC'],
                ['CCMC'],
            ],
        ];
    }

    /**
     * @dataProvider filterSourcesProvider
     */
    public function testItShouldFilterSources(array $input, array $expected): void
    {
        $this->assertEquals($expected, EventsApi::filterSources($input));
    }
}
