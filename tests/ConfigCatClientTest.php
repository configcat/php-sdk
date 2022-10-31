<?php

namespace ConfigCat\Tests;

use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\EvaluationDetails;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use ConfigCat\User;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use ReflectionException;

class ConfigCatClientTest extends TestCase
{
    public function testConstructEmptySdkKey()
    {
        $this->expectException(InvalidArgumentException::class);
        ConfigCatClient::get("");
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructDefaults()
    {
        $client = ConfigCatClient::get("testConstructDefaults");

        $logger = $this->getReflectedValue($client, "logger");
        $this->assertInstanceOf(InternalLogger::class, $logger);

        $cache = $this->getReflectedValue($client, "cache");
        $this->assertInstanceOf(ArrayCache::class, $cache);

        $cacheRefreshInterval = $this->getReflectedValue($client, "cacheRefreshInterval");
        $this->assertEquals(60, $cacheRefreshInterval);
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructLoggerOption()
    {
        $logger = new NullLogger();
        $client = ConfigCatClient::get("testConstructLoggerOption", [
            ClientOptions::LOGGER => $logger,
            ClientOptions::LOG_LEVEL => LogLevel::ERROR,
            ClientOptions::EXCEPTIONS_TO_IGNORE => [InvalidArgumentException::class]
        ]);
        $internalLogger = $this->getReflectedValue($client, "logger");

        $externalLogger = $this->getReflectedValue($internalLogger, "logger");
        $globalLevel = $this->getReflectedValue($internalLogger, "globalLevel");
        $exceptions = $this->getReflectedValue($internalLogger, "exceptionsToIgnore");

        $this->assertSame($logger, $externalLogger);
        $this->assertSame(LogLevel::ERROR, $globalLevel);
        $this->assertArraySubset([InvalidArgumentException::class], $exceptions);
    }

    public function testConstructCacheOption()
    {
        $cache = new ArrayCache();
        $client = ConfigCatClient::get("testConstructCacheOption", [ClientOptions::CACHE => $cache]);
        $this->assertAttributeSame($cache, "cache", $client);
    }

    public function testConstructCacheRefreshIntervalOption()
    {
        $client = ConfigCatClient::get("testConstructCacheRefreshIntervalOption", [ClientOptions::CACHE_REFRESH_INTERVAL => 20]);
        $this->assertAttributeSame(20, "cacheRefreshInterval", $client);
    }

    public function testGetValueFailedFetch()
    {
        $client = ConfigCatClient::get("testGetValueFailedFetch", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(400)
        ])]);

        $value = $client->getValue("key", false);
        $this->assertFalse($value);
    }

    public function testGetAllKeysFailedFetch()
    {
        $client = ConfigCatClient::get("testGetAllKeysFailedFetch", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(400)
        ])]);

        $keys = $client->getAllKeys();
        $this->assertEmpty($keys);
    }

    public function testForceRefresh()
    {
        $cache = $this->getMockBuilder(ConfigCache::class)->getMock();
        $client = ConfigCatClient::get("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA",
            [ClientOptions::CACHE => $cache]);

        $cache
            ->expects(self::once())
            ->method("store");

        $client->forceRefresh();
    }

    public function testKeyNotExist()
    {
        $client = ConfigCatClient::get("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA");
        $value = $client->getValue("nonExistingKey", false);

        $this->assertFalse($value);
    }

    public function testGetVariationId()
    {
        $client = ConfigCatClient::get("testGetVariationId", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getVariationId("second", null);

        $this->assertEquals("fakeIdSecond", $value);
    }

    public function testGetVariationIdDefault()
    {
        $client = ConfigCatClient::get("testGetVariationIdDefault", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getVariationId("nonexisting", null);

        $this->assertNull($value);
    }

    public function testGetAllVariationIds()
    {
        $client = ConfigCatClient::get("testGetAllVariationIds", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getAllVariationIds();

        $this->assertEquals(["fakeIdFirst", "fakeIdSecond"], $value);
    }

    public function testGetAllVariationIdsEmpty()
    {
        $client = ConfigCatClient::get("testGetAllVariationIdsEmpty", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(400)
        ])]);
        $value = $client->getAllVariationIds();

        $this->assertEmpty($value);
    }

    public function testGetKeyAndValue()
    {
        $client = ConfigCatClient::get("testGetKeyAndValue", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getKeyAndValue("fakeIdSecond");

        $this->assertEquals("second", $value->getKey());
        $this->assertTrue($value->getValue());
    }

    public function testGetKeyAndValueNull()
    {
        $client = ConfigCatClient::get("testGetKeyAndValueNull", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getKeyAndValue("nonexisting");

        $this->assertNull($value);
    }

    public function testGetAllValues()
    {
        $client = ConfigCatClient::get("testGetAllValues", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}")]
            ),
        ]);
        $value = $client->getAllValues();

        $this->assertEquals(["first" => false, "second" => true], $value);
    }

    public function testDefaultUser()
    {
        $client = ConfigCatClient::get("testDefaultUser", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules())
        ])]);

        $user1 = new User("test@test1.com");
        $user2 = new User("test@test2.com");

        $client->setDefaultUser($user1);

        $value = $client->getValue("key", "");
        $this->assertEquals("fake1", $value);

        $value = $client->getValue("key", "", $user2);
        $this->assertEquals("fake2", $value);

        $client->clearDefaultUser();

        $value = $client->getValue("key", "");
        $this->assertEquals("def", $value);
    }

    public function testDefaultUserVariationId()
    {
        $client = ConfigCatClient::get("testDefaultUserVariationId", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules())
        ])]);

        $user1 = new User("test@test1.com");
        $user2 = new User("test@test2.com");

        $client->setDefaultUser($user1);

        $value = $client->getVariationId("key", "");
        $this->assertEquals("id1", $value);

        $value = $client->getVariationId("key", "", $user2);
        $this->assertEquals("id2", $value);

        $client->clearDefaultUser();

        $value = $client->getVariationId("key", "");
        $this->assertEquals("defVar", $value);
    }

    public function testHooks()
    {
        $client = ConfigCatClient::get("getTestClientWithError", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [
                    new Response(200, [], "{ \"f\" : { \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}"),
                    new Response(400, [], "")
                ]
            ),
        ]);

        $evaluated = false;
        $error = false;
        $changed = false;
        $message = "";
        $client->hooks()->addOnFlagEvaluated(function($details) use (&$evaluated) {
            $evaluated = true;
        });
        $client->hooks()->addOnConfigChanged(function($settings) use (&$changed) {
            $changed = true;
        });
        $client->hooks()->addOnError(function($err) use (&$error, &$message) {
            $error = true;
            $message = $err;
        });

        $client->getValue("first", false);
        $result = $client->forceRefresh();


        $this->assertTrue($evaluated);
        $this->assertTrue($error);
        $this->assertEquals("Double-check your SDK Key at https://app.configcat.com/sdkkey. Received unexpected response: 400", $message);
        $this->assertTrue($changed);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Double-check your SDK Key at https://app.configcat.com/sdkkey. Received unexpected response: 400", $result->getError());
    }

    public function testEvalDetails()
    {
        $client = ConfigCatClient::get("testEvalDetails", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules())
        ])]);

        $details = $client->getValueDetails("key", "", new User("test@test1.com"));

        $this->assertEquals("fake1", $details->getValue());
        $this->assertEquals("id1", $details->getVariationId());
        $this->assertNull($details->getError());
        $this->assertEquals("key", $details->getKey());
        $this->assertEquals("test@test1.com", $details->getUser()->getIdentifier());
        $this->assertEquals("Identifier", $details->getMatchedEvaluationRule()["a"]);
        $this->assertEquals("@test1.com", $details->getMatchedEvaluationRule()["c"]);
        $this->assertEquals(2, $details->getMatchedEvaluationRule()["t"]);
        $this->assertNull($details->getMatchedEvaluationPercentageRule());
        $this->assertTrue($details->getFetchTimeUnixSeconds() > 0);
        $this->assertFalse($details->isDefaultValue());
    }

    public function testEvalDetailsHook()
    {
        $client = ConfigCatClient::get("testEvalDetailsHook", [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules())
        ])]);

        $called = false;
        $client->hooks()->addOnFlagEvaluated(function (EvaluationDetails $details) use (&$called) {
            $this->assertEquals("fake1", $details->getValue());
            $this->assertEquals("id1", $details->getVariationId());
            $this->assertNull($details->getError());
            $this->assertEquals("key", $details->getKey());
            $this->assertEquals("test@test1.com", $details->getUser()->getIdentifier());
            $this->assertEquals("Identifier", $details->getMatchedEvaluationRule()["a"]);
            $this->assertEquals("@test1.com", $details->getMatchedEvaluationRule()["c"]);
            $this->assertEquals(2, $details->getMatchedEvaluationRule()["t"]);
            $this->assertNull($details->getMatchedEvaluationPercentageRule());
            $this->assertFalse($details->isDefaultValue());
            $this->assertTrue($details->getFetchTimeUnixSeconds() > 0);
            $called = true;
        });

        $client->getValue("key", "", new User("test@test1.com"));

        $this->assertTrue($called);
    }

    public function testTimout()
    {
        $client = ConfigCatClient::get("testTimout", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new ConnectException("timeout", new Request("GET", "test"))
            ]),
        ]);
        $value = $client->getValue("test", "def");

        $this->assertEquals("def", $value);
    }

    public function testSingleton()
    {
        $client1 = ConfigCatClient::get("testSingleton");
        $client2 = ConfigCatClient::get("testSingleton");

        $this->assertSame($client1, $client2);
    }

    public function testHttpException()
    {
        $client = ConfigCatClient::get("testHttpException", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new RequestException("failed", new Request("GET", "test"))
            ]),
        ]);
        $value = $client->getValue("test", "def");

        $this->assertEquals("def", $value);
    }

    public function testGeneralException()
    {
        $client = ConfigCatClient::get("testGeneralException", [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new Exception("failed")
            ]),
        ]);
        $value = $client->getValue("test", "def");

        $this->assertEquals("def", $value);
    }

    /**
     * @throws ReflectionException
     */
    private function getReflectedValue($object, $propertyName)
    {
        $reflection = new \ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        return $property->getValue($object);
    }
}
