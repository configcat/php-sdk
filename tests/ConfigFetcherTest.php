<?php

namespace ConfigCat\Tests;

use ConfigCat\ConfigFetcher;
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNullApiKey()
    {
        new ConfigFetcher(null, new NullLogger());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructEmptyApiKey()
    {
        new ConfigFetcher("", new NullLogger());
    }

    public function testConstructDefaults()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger());
        $options = $fetcher->getRequestOptions();

        $this->assertEquals(10, $options['connect-timeout']);
        $this->assertEquals(30, $options['timeout']);
        $this->assertArrayHasKey("headers", $options);
    }

    public function testConstructConnectTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger(), ['request-options' => [
            'connect-timeout' => 5
        ]]);
        $options = $fetcher->getRequestOptions();
        $this->assertEquals(5, $options['connect-timeout']);
    }

    public function testConstructRequestTimeoutOption()
    {
        $fetcher = new ConfigFetcher("api", new NullLogger(), ['request-options' => [
            'timeout' => 5
        ]]);
        $options = $fetcher->getRequestOptions();
        $this->assertEquals(5, $options['timeout']);
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
