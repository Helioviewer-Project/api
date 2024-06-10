<?php declare(strict_types=1);
/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

require_once __DIR__ . "/MockPhpStream.php";

use PHPUnit\Framework\TestCase;
use Helioviewer\Api\Request\RequestParams;
use Helioviewer\Api\Request\RequestException;

final class RequestParamsTest extends TestCase
{
    public function testItShouldCollectParamsEmptyArrIfThereIsNoRequestVariables()
    {
		$params = RequestParams::collect();

        $this->assertEmpty($params);
    }

    public function testItShouldCollectParamsIfGetRequest()
    {
        $_GET = ['get_param' => 'value1'];
		$params = RequestParams::collect();

        $this->assertArrayHasKey('get_param', $params);
        $this->assertEquals('value1', $params['get_param']);
    }

    public function testItShouldMergeParamsIfThereIsGetAndPostParamsTogether()
    {
        $_GET = ['get_param' => 'value1'];
        $_POST = ['post_param' => 'value2'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        $params = RequestParams::collect();

        $this->assertArrayHasKey('get_param', $params);
        $this->assertEquals('value1', $params['get_param']);

        $this->assertArrayHasKey('post_param', $params);
        $this->assertEquals('value2', $params['post_param']);
    }

    public function testItShouldMergeParamsIfThereIsGetAndPutParamsTogether()
    {
        $_GET = ['get_param' => 'value1'];
        $_POST = ['post_param' => 'value2'];
        $_SERVER['REQUEST_METHOD'] = 'PUT';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';

        $params = RequestParams::collect();

        $this->assertArrayHasKey('get_param', $params);
        $this->assertEquals('value1', $params['get_param']);

        $this->assertArrayHasKey('post_param', $params);
        $this->assertEquals('value2', $params['post_param']);
    }

    public function testItShouldMergeJsonTogetherWithGetParams()
    {
        $_GET = ['get_param' => 'value1'];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $json = json_encode(['json_param' => 'value3']);
        $this->setPhpInput($json);

        $params = RequestParams::collect();

        $this->assertArrayHasKey('get_param', $params);
        $this->assertEquals('value1', $params['get_param']);

        $this->assertArrayHasKey('json', $params);
        $this->assertArrayHasKey('json_param', $params['json']);
        $this->assertEquals('value3', $params['json']['json_param']);
    }

    public function testItShouldCorrectlyThrowRequestExceptionForInvalidJson()
    {
        $_GET = ['get_param' => 'value1'];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $invalidJson = '{"json_param": "value3"'; // Invalid JSON
        $this->setPhpInput($invalidJson);

        $this->expectException(RequestException::class);
        $this->expectExceptionMessage('Syntax error');

        $params = RequestParams::collect();
    }

    protected function setPhpInput($input): void
    {
        file_put_contents('php://input', $input);
    }

    protected function tearDown(): void
    {
        stream_wrapper_restore('php');
    }
    protected function setUp(): void
    {
        $_GET = [];
        $_POST = [];
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['CONTENT_TYPE'] = '';
        stream_wrapper_unregister('php');
        stream_wrapper_register('php', 'MockPhpStream');
    }

}

