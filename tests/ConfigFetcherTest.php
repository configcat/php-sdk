<?php

namespace ConfigCat;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
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

        $response = $fetcher->fetch("");

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
}
