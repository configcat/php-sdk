<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigFetcher;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class DataGovernanceTest extends TestCase
{
    final public const JSON_TEMPLATE = '{ "p": { "u": "%s", "r": %d }, "f": {} }';
    final public const CUSTOM_CDN_URL = 'https://custom-cdn.configcat.com';

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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(1, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($body, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(1, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($body, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(1, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($body, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(2, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertEquals(json_decode($secondBody, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(2, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertEquals(json_decode($secondBody, true), $response->getBody());
    }

    public function testShouldRedirectToAnotherServerWhenWrongIsCached()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 1);
        $secondBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 0);
        $responses = [
            new Response(200, [], $firstBody),
            new Response(200, [], $secondBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler);

        // Act
        $response = $fetcher->fetch('', ConfigFetcher::EU_ONLY_URL);

        // Assert
        $this->assertEquals(2, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($secondBody, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(3, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertContains($requests[2]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($firstBody, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(3, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::EU_ONLY_URL);
        $this->assertContains($requests[2]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($firstBody, true), $response->getBody());
    }

    public function testShouldRespectCustomUrlWhenNotForced()
    {
        // Arrange
        $requests = [];
        $firstBody = sprintf(self::JSON_TEMPLATE, ConfigFetcher::GLOBAL_URL, 1);
        $responses = [
            new Response(200, [], $firstBody),
        ];

        $handler = $this->getHandlerStack($responses, $requests);
        $fetcher = $this->getFetcher($handler, self::CUSTOM_CDN_URL);

        // Act
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(1, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), self::CUSTOM_CDN_URL);
        $this->assertEquals(json_decode($firstBody, true), $response->getBody());
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
        $response = $fetcher->fetch('', '');

        // Assert
        $this->assertEquals(2, is_countable($requests) ? \count($requests) : 0);
        $this->assertContains($requests[0]['request']->getUri()->getHost(), self::CUSTOM_CDN_URL);
        $this->assertContains($requests[1]['request']->getUri()->getHost(), ConfigFetcher::GLOBAL_URL);
        $this->assertEquals(json_decode($secondBody, true), $response->getBody());
    }

    private function getHandlerStack(array $responses, array &$container = [])
    {
        $history = Middleware::history($container);
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push($history);

        return $stack;
    }

    private function getFetcher($handler, $customUrl = '')
    {
        return new ConfigFetcher('fakeKey', Utils::getTestLogger(), [
            ClientOptions::CUSTOM_HANDLER => $handler,
            ClientOptions::BASE_URL => $customUrl,
        ]);
    }
}
