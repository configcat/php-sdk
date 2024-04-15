<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigFetcher;
use ConfigCat\ConfigJson\Config;
use ConfigCat\Tests\Helpers\Utils;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DataGovernanceTest extends TestCase
{
    const JSON_TEMPLATE = '{"p":{"u":"%s","r":%d}}';
    const CUSTOM_CDN_URL = 'https://custom-cdn.configcat.com';

    public function testShouldStayOnServer()
    {
        // Arrange
        $requests = [];
        $body = sprintf(self::JSON_TEMPLATE, 'https://fakeUrl', 0);
        $responses = [
            new Response(200, [], $body),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(1, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($body), $response->getConfigEntry()->getConfig());
    }

    public function testShouldStayOnSameUrlWithRedirect()
    {
        // Arrange
        $requests = [];
        $body = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 1);
        $responses = [
            new Response(200, [], $body),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(1, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($body), $response->getConfigEntry()->getConfig());
    }

    public function testShouldStayOnSameUrlEvenWhenForced()
    {
        // Arrange
        $requests = [];
        $body = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 2);
        $responses = [
            new Response(200, [], $body),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(1, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($body), $response->getConfigEntry()->getConfig());
    }

    public function testShouldRedirectToAnotherServer()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 1);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 0);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(2, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertEquals(Config::deserialize($secondBody), $response->getConfigEntry()->getConfig());
    }

    public function testShouldRedirectToAnotherServerWhenForced()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 2);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 0);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(2, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertEquals(Config::deserialize($secondBody), $response->getConfigEntry()->getConfig());
    }

    public function testShouldBreakRedirectLoop()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 1);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 1);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
            new Response(200, [], $firstBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(3, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertStringContainsString($requests[2]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($firstBody), $response->getConfigEntry()->getConfig());
    }

    public function testShouldBreakRedirectLoopWhenForced()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::EU_ONLY_URL, 2);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 2);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
            new Response(200, [], $firstBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(3, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertStringContainsString($requests[2]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($firstBody), $response->getConfigEntry()->getConfig());
    }

    public function testShouldRespectCustomUrlWhenNotForced()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 1);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $firstBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler, self::CUSTOM_CDN_URL);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(1, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), self::CUSTOM_CDN_URL);
        $this->assertEquals(Config::deserialize($firstBody), $response->getConfigEntry()->getConfig());

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(2, $requests);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), self::CUSTOM_CDN_URL);
        $this->assertEquals(Config::deserialize($firstBody), $response->getConfigEntry()->getConfig());
    }

    public function testShouldNotRespectCustomUrlWhenForced()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 2);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 0);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler, self::CUSTOM_CDN_URL);

        // Act
        $response = $fetcher->fetch('');

        // Assert
        $this->assertCount(2, $requests);
        $this->assertStringContainsString($requests[0]['request']->getUri()->getHost(), self::CUSTOM_CDN_URL);
        $this->assertStringContainsString($requests[1]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(Config::deserialize($secondBody), $response->getConfigEntry()->getConfig());
    }

    private function getHandlerStack(array $responses, array &$container = []): HandlerStack
    {
        $history = Middleware::history($container);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push($history);

        return $stack;
    }

    private function getFetcher($handler, $customUrl = ''): ConfigFetcher
    {
        return new ConfigFetcher('fakeKey', Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => $handler,
            ClientOptions::BASE_URL => $customUrl,
        ]);
    }
}
