<?php declare(strict_types=1);

/**
 * @author Kasim Necdet Percinel <kasim.n.percinel@nasa.gov>
 */

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;

final class SentryIssue1Test extends TestCase
{
    /**
    * @group regression
    * @group integration
    **/
    public function testItShouldDumpProperResponseCodeAndReasonPhraseIfThereIsNoActionGiven(): void
    {
        //Create a Guzzle client
        $client = new Client();

        // Send a GET request to the specified URL
        $response = $client->get(HV_LOCAL_TEST_URL, [
            'http_errors' => false
        ]);

        // Assert Status code and Reason Phrase
        $this->assertEquals($response->getStatusCode(), 400);
        $this->assertEquals($response->getReasonPhrase(), 'Bad Request');
    }
}
