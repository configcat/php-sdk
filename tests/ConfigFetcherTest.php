<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigFetcher;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class ConfigFetcherTest extends TestCase
{
    private string $mockSdkKey = 'testSdkKey';
    private string $mockEtag = 'testEtag';
    private string $mockBody = '{"key": "value"}';

    public function testFetchOk()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
                new Response(200, [ConfigFetcher::ETAG_HEADER => $this->mockEtag], $this->mockBody), ])), ]);

        $response = $fetcher->fetch('old_etag');

        $this->assertTrue($response->isFetched());
        $this->assertEquals($this->mockEtag, $response->getConfigEntry()->getEtag());
        $this->assertEquals('value', $response->getConfigEntry()->getConfig()['key']);
    }

    public function testFetchNotModified()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
                new Response(304, [ConfigFetcher::ETAG_HEADER => $this->mockEtag]), ])), ]);

        $response = $fetcher->fetch('');

        $this->assertTrue($response->isNotModified());
        $this->assertEmpty($response->getConfigEntry()->getETag());
        $this->assertEmpty($response->getConfigEntry()->getConfig());
    }

    public function testFetchFailed()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([new Response(400)])), ]);

        $response = $fetcher->fetch('');

        $this->assertTrue($response->isFailed());
        $this->assertEmpty($response->getConfigEntry()->getETag());
        $this->assertEmpty($response->getConfigEntry()->getConfig());
    }

    public function testFetchInvalidJson()
    {
        $fetcher = new ConfigFetcher($this->mockSdkKey, Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
                new Response(200, [], '{"key": value}'), ])), ]);

        $response = $fetcher->fetch('');

        $this->assertTrue($response->isFailed());
        $this->assertEmpty($response->getConfigEntry()->getETag());
        $this->assertEmpty($response->getConfigEntry()->getConfig());
    }

    public function testConstructEmptySdkKey()
    {
        $this->expectException(InvalidArgumentException::class);
        new ConfigFetcher('', Utils::getNullLogger());
    }

    public function testTimeoutException()
    {
        $fetcher = new ConfigFetcher('api', Utils::getTestLogger(), [ClientOptions::CUSTOM_HANDLER => HandlerStack::create(new MockHandler([
            new ConnectException('timeout', new Request('GET', 'test')),
        ]))]);
        $response = $fetcher->fetch('');
        $this->assertTrue($response->isFailed());
    }

    public function testIntegration()
    {
        $fetcher = new ConfigFetcher('PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA', Utils::getTestLogger());
        $response = $fetcher->fetch('');

        $this->assertTrue($response->isFetched());

        $notModifiedResponse = $fetcher->fetch($response->getConfigEntry()->getEtag());

        $this->assertTrue($notModifiedResponse->isNotModified());
    }
}
