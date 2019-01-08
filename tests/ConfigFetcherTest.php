<?php

namespace ConfigCat;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConfigFetcherTest extends TestCase
{
    private $mockApiKey = "testApiKey";
    private $mockEtag = "testEtag";
    private $mockBody = "{\"key\": \"value\"}";

    public function testFetchOk()
    {
        $fetcher = new ConfigFetcher($this->mockApiKey, new NullLogger(), ['custom-handler' => new MockHandler([
            new Response(200, [ConfigFetcher::ETAG_HEADER => $this->mockEtag], $this->mockBody)
        ])]);

        $response = $fetcher->fetch("old_etag");

        $this->assertTrue($response->isFetched());
        $this->assertEquals($this->mockEtag, $response->getETag());
        $this->assertEquals("value", $response->getBody()['key']);
    }

    public function testFetchNotModified()
    {
        $fetcher = new ConfigFetcher($this->mockApiKey, new NullLogger(), ['custom-handler' => new MockHandler([
            new Response(304, [ConfigFetcher::ETAG_HEADER => $this->mockEtag])
        ])]);

        $response = $fetcher->fetch("");

        $this->assertTrue($response->isNotModified());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    public function testFetchFailed()
    {
        $fetcher = new ConfigFetcher($this->mockApiKey, new NullLogger(), ['custom-handler' => new MockHandler([
            new Response(400)
        ])]);

        $response = $fetcher->fetch("");

        $this->assertTrue($response->isFailed());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    public function testFetchInvalidJson()
    {
        $fetcher = new ConfigFetcher($this->mockApiKey, new NullLogger(), ['custom-handler' => new MockHandler([
            new Response(200, [], "{\"key\": value}")
        ])]);

        $response = $fetcher->fetch("");

        $this->assertTrue($response->isFailed());
        $this->assertNull($response->getETag());
        $this->assertNull($response->getBody());
    }

    public function testConstructNullApiKey()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new ConfigFetcher(null, new NullLogger());
    }

    public function testConstructEmptyApiKey()
    {
        $this->setExpectedException(InvalidArgumentException::class);
        new ConfigFetcher("", new NullLogger());
    }

    public function testConstructDefaults()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger());
        $this->assertAttributeEquals(10, "connectTimeout", $fetcher);
        $this->assertAttributeEquals(30, "requestTimeout", $fetcher);
    }

    public function testConstructConnectTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger(), ['connect-timeout' => 5]);
        $this->assertAttributeEquals(5, "connectTimeout", $fetcher);
    }

    public function testConstructRequestTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger(), ['timeout' => 5]);
        $this->assertAttributeEquals(5, "requestTimeout", $fetcher);
    }

    public function testIntegration()
    {
        $fetcher = new ConfigFetcher("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA", new NullLogger());
        $response = $fetcher->fetch("");

        $this->assertTrue($response->isFetched());

        $notModifiedResponse = $fetcher->fetch($response->getETag());

        $this->assertTrue($notModifiedResponse->isNotModified());
    }
}
