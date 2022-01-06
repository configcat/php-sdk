<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigFetcher;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConfigFetcherTest extends TestCase
{
    private $mockSdkKey = "testSdkKey";
    private $mockEtag = "testEtag";
    private $mockBody = "{\"key\": \"value\"}";

    public function testFetchOk()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new Response(200, [ConfigFetcher::ETAG_HEADER => $this->mockEtag], $this->mockBody)
        ]))]);

        $response = $fetcher->fetch("old_etag", "");

        $this->assertTrue($response->isFetched());
        $this->assertEquals($this->mockEtag, $response->getETag());
        $this->assertEquals("value", $response->getBody()['key']);
    }

    public function testFetchNotModified()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new Response(304, [ConfigFetcher::ETAG_HEADER => $this->mockEtag])
        ]))]);

        $response = $fetcher->fetch("", "");

        $this->assertTrue($response->isNotModified());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    public function testFetchFailed()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new Response(400)
        ]))]);

        $response = $fetcher->fetch("", "");

        $this->assertTrue($response->isFailed());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    public function testFetchInvalidJson()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new Response(200, [], "{\"key\": value}")
        ]))]);

        $response = $fetcher->fetch("", "");

        $this->assertTrue($response->isFailed());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNullSdkKey()
    {
        new ConfigFetcher(null, new NullLogger());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructEmptySdkKey()
    {
        new ConfigFetcher("", new NullLogger());
    }

    public function testConstructDefaults()
    {
        $fetcher = new ConfigFetcher("api", Utils::getTestLogger());
        $options = $fetcher->getRequestOptions();

        $this->assertEquals(10, $options[RequestOptions::CONNECT_TIMEOUT]);
        $this->assertEquals(30, $options[RequestOptions::TIMEOUT]);
        $this->assertArrayHasKey("headers", $options);
    }

    public function testConstructConnectTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", Utils::getTestLogger(), [ClientOptions::REQUEST_OPTIONS => [
            RequestOptions::CONNECT_TIMEOUT => 5
        ]]);
        $options = $fetcher->getRequestOptions();
        $this->assertEquals(5, $options[RequestOptions::CONNECT_TIMEOUT]);
    }

    public function testConstructRequestTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", Utils::getTestLogger(), [ClientOptions::REQUEST_OPTIONS => [
            RequestOptions::TIMEOUT => 5
        ]]);
        $options = $fetcher->getRequestOptions();
        $this->assertEquals(5, $options[RequestOptions::TIMEOUT]);
    }

    public function testTimeoutException()
    {
        $fetcher = new ConfigFetcher("api", Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new ConnectException("timeout", new Request("GET", "test"))
        ]))]);
        $response = $fetcher->fetch("", "");
        $this->assertTrue($response->isFailed());
    }

    public function testIntegration()
    {
        $fetcher = new ConfigFetcher("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA", Utils::getTestLogger());
        $response = $fetcher->fetch("", "");

        $this->assertTrue($response->isFetched());

        $notModifiedResponse = $fetcher->fetch($response->getETag(), "");

        $this->assertTrue($notModifiedResponse->isNotModified());
    }
}
