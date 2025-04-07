<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\VoidClient;
use Helioviewer\Api\Sentry\Client;
use Helioviewer\Api\Sentry\ClientInterface;

final class SentryTest extends TestCase
{
    protected function setUp(): void
    {
        Sentry::$client = null;
        Sentry::$contexts = [];
    }

    public static function GetDisabledConfigs(): array
    {
        return [
            [["enabled"=>false]],
            [["enabled"=>'false']],
            [["enabled"=>'true']],
            [["foo"=>'true']],
            [["bar"=>'true']],
        ];
    }
    /**
     * @dataProvider GetDisabledConfigs
     */
    public function testItShouldUseVoidClientWhenNotEnabled($config)
    {
        Sentry::init($config);
        $this->assertInstanceOf(VoidClient::class, Sentry::$client);
    }


    public function testItShouldUseSentryClientWhenEnabled()
    {
        Sentry::init([
            'enabled' => true,
            'sample_rate' => '1',
            'environment' => 'env',
            'dsn' => 'http://boo@foo-sentry/1220',
        ]);
        $this->assertInstanceOf(Client::class, Sentry::$client);
    }

    public function testItShouldUseGivenClientWhenGivenFromConfig()
    {
        $mock_client = $this->createMock(ClientInterface::class);
        Sentry::init([
            'client' => $mock_client,
        ]);
        $this->assertEquals(Sentry::$client, $mock_client);
    }

    public function testItShouldCaptureExceptionWithSentryClient()
    {
        $random_exception = new \Exception(sprintf("Rand%d Exception", rand()));
        $mock_client = $this->createMock(ClientInterface::class);

        $mock_client->expects($this->once())
            ->method('capture')
            ->with($this->identicalTo($random_exception));

        Sentry::init([
            'client' => $mock_client,
        ]);
        Sentry::capture($random_exception);
    }

    public function testItShouldCaptureMessageWithSentryClient()
    {
        $random_message = sprintf("Rand%d message", rand());

        $mock_client = $this->createMock(ClientInterface::class);

        $mock_client->expects($this->once())
            ->method('message')
            ->with($this->identicalTo($random_message));

        Sentry::init([
            'client' => $mock_client,
        ]);
        Sentry::message($random_message);
    }

    public function testItShouldThrowExceptionWhenNotInitalizedForExceptions()
    {
        $this->expectException(\RuntimeException::class);
        Sentry::capture(new \Exception("exception"));
    }

    public function testItShouldThrowExceptionWhenNotInitalizedForMessages()
    {
        $this->expectException(\RuntimeException::class);
        Sentry::message("foo-message");
    }

    public function testItShouldThrowExceptionWhenNotInitalizedForContexts()
    {
        $this->expectException(\RuntimeException::class);
        Sentry::setContext("foo-context",['a'=>'b']);
    }

    public function testItShouldThrowExceptionWhenNotInitalizedForTags()
    {
        $this->expectException(\RuntimeException::class);
        Sentry::setTag("foo","baz");
    }

    public function testItShouldSendGivenContextToSentry()
    {
        $random_context = sprintf("Rand%d context", rand());

        $random_params = [
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
            sprintf("rand%d param", rand()) => sprintf("rand%d value", rand()),
        ];

        $mock_client = $this->createMock(ClientInterface::class);

        $mock_client->expects($this->once())
            ->method('setContext')
            ->with($this->identicalTo($random_context), $this->identicalTo($random_params));

        Sentry::init([
            'client' => $mock_client,
        ]);
        Sentry::setContext($random_context, $random_params);
    }


    public static function GetInvalidParams(): array
    {
        return [
            [["foo"]],
            [["foo" => "bar", 12 => "baz"]],
            [["foo" => "bar", strval(M_PI) => "baz"]],
        ];
    }
    /**
     * @dataProvider GetInvalidParams
     */
    public function testItShouldRefuseInvalidParams(array $invalid_params)
    {
        $this->expectException(\InvalidArgumentException::class);

        $mock_client = $this->createMock(ClientInterface::class);

        Sentry::init([
            'client' => $mock_client,
        ]);

        Sentry::setContext('foo_context', $invalid_params);
    }


    public static function GetInvalidTags(): array
    {
        return [
            ["foo",""],
            ["", ""],
            ["", "foo"],
        ];
    }
    /**
     * @dataProvider GetInvalidTags
     */
    public function testItShouldRefuseInvalidTags(string $invalid_tag, string $invalid_tag_value)
    {
        $this->expectException(\InvalidArgumentException::class);

        $mock_client = $this->createMock(ClientInterface::class);

        Sentry::init([
            'client' => $mock_client,
        ]);

        Sentry::setTag($invalid_tag, $invalid_tag_value);
    }

    /**
     * @dataProvider GetContextParams
     */
    public function testItShouldMergeGivenContextParams($params, $result)
    {
        $mock_client = $this->createMock(ClientInterface::class);

        Sentry::init([
            'client' => $mock_client,
        ]);

        foreach($params as $p) {
            Sentry::setContext("foo-context", $p);
        }

        $this->assertEquals(Sentry::$contexts, [
            'foo-context' => $result
        ]);
    }

    public static function GetContextParams(): array
    {
        return [
            [
                // case 1
                // params
                [
                    // param1
                    ['foo' => 'bar'],
                    // param2
                    ['foo1' => 'bar1','foo2'=>'bar2']
                ],
                // result
                ["foo"=>'bar', 'foo1'=>'bar1', 'foo2'=>'bar2']
            ],
            [
                // case 2
                // params
                [
                    // param1
                    ['foo' => 'bar'],
                    // param2
                    ['foo1' => 'bar1','foo'=>'baz']
                ],
                // result
                ["foo"=>'baz', 'foo1'=>'bar1']
            ],

            [
                // case 3
                // params
                [
                    // param1
                    ['foo' => 'bar'],
                    // param2
                    ['foo' => 'bar1','foo1'=>'baz'],
                    // param3
                    ['foo' => 'baz','foo1'=>'baz1'],
                    // param3
                    ['foo3' => 'baz','foo5'=>'baz1'],
                ],
                // result
                ["foo"=>'baz', 'foo1'=>'baz1', 'foo3' => 'baz', 'foo5' => 'baz1']
            ],
        ];
    }

}

