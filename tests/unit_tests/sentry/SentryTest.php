<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;

use Helioviewer\Api\Sentry\Sentry;
use Helioviewer\Api\Sentry\VoidClient;
use Helioviewer\Api\Sentry\ClientInterface;

final class SentryTest extends TestCase
{
    public function testItShouldMaintainOnlyOneInstanceOfSentry()
    {
        $sentry_1 = Sentry::get(['enabled' => false]);
        $sentry_2 = Sentry::get(['enabled' => false]);
        $sentry_3 = Sentry::get(['enabled' => false]);

        $this->assertEquals($sentry_1, $sentry_2);
        $this->assertEquals($sentry_2, $sentry_3);
        $this->assertEquals($sentry_1, $sentry_3);
    }

    public function testItShouldCaptureExceptionForSentry()
    {
        $random_exception = new \Exception(sprintf("Rand%d Exception", rand()));

        $mock_client = $this->createMock(ClientInterface::class);

        $mock_client->expects($this->once())
            ->method('capture')
            ->with($this->identicalTo($random_exception));

        $sentry = new Sentry($mock_client);
        $sentry->capture($random_exception);

    }

    public function testItShouldCaptureMessageForSentry()
    {
        $random_message = sprintf("Rand%d message", rand());

        $mock_client = $this->createMock(ClientInterface::class);

        $mock_client->expects($this->once())
            ->method('message')
            ->with($this->identicalTo($random_message));

        $sentry = new Sentry($mock_client);
        $sentry->message($random_message);
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

        $sentry = new Sentry($mock_client);
        $sentry->setContext($random_context, $random_params);
    }
    

    public static function GetInvalidParams(): array 
    {
        return [
            [["foo"]],
            [["foo" => "bar", 12 => "baz"]],
            [["foo" => "bar", M_PI => "baz"]],
        ];
    }
    /**
     * @dataProvider GetInvalidParams
     */
    public function testItShouldRefuseInvalidParams(array $params)
    {
        $this->expectException(\InvalidArgumentException::class);

        $context = sprintf("invalid%d", rand());
        $invalid_params = ["foo"];

        $sentry = new Sentry(new VoidClient([]));
        $sentry->setContext($context, $invalid_params);
    }

    /**
     * @dataProvider GetContextParams
     */
    public function testItShouldMergeGivenContextParams($params, $result) 
    {
        $mock_client = $this->createMock(ClientInterface::class);

        $sentry = new Sentry($mock_client);

        foreach($params as $p) {
            $sentry->setContext("foo-context", $p);
        }

        $this->assertEquals($sentry->getContexts(), [
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

