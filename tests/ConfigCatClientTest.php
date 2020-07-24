<?php

namespace ConfigCat\Tests;

use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\ConfigCatClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConfigCatClientTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNullSdkKey()
    {
        new ConfigCatClient(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructEmptySdkKey()
    {
        new ConfigCatClient("");
    }

    public function testConstructDefaults()
    {
        $client = new ConfigCatClient("key");
        $this->assertAttributeInstanceOf(Logger::class, "logger", $client);
        $this->assertAttributeInstanceOf(ArrayCache::class, "cache", $client);
        $this->assertAttributeEquals(60, "cacheRefreshInterval", $client);
    }

    public function testConstructLoggerOption()
    {
        $logger = new NullLogger();
        $client = new ConfigCatClient("key", ['logger' => $logger]);
        $this->assertAttributeSame($logger, "logger", $client);
    }

    public function testConstructCacheOption()
    {
        $cache = new ArrayCache();
        $client = new ConfigCatClient("key", ['cache' => $cache]);
        $this->assertAttributeSame($cache, "cache", $client);
    }

    public function testConstructCacheRefreshIntervalOption()
    {
        $client = new ConfigCatClient("key", ['cache-refresh-interval' => 20]);
        $this->assertAttributeSame(20, "cacheRefreshInterval", $client);
    }

    public function testGetValueFailedFetch()
    {
        $client = new ConfigCatClient("sdkKey", ['custom-handler' => new MockHandler([
            new Response(400)
        ])]);

        $value = $client->getValue("key", false);
        $this->assertFalse($value);
    }

    public function testGetAllKeysFailedFetch()
    {
        $client = new ConfigCatClient("sdkKey", ['custom-handler' => new MockHandler([
            new Response(400)
        ])]);

        $keys = $client->getAllKeys();
        $this->assertEmpty($keys);
    }

    public function testForceRefresh()
    {
        $cache = $this->getMockBuilder(ConfigCache::class)->getMock();
        $client = new ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA", ['cache' => $cache]);

        $cache
            ->expects(self::once())
            ->method("store");

        $client->forceRefresh();
    }

    public function testKeyNotExist()
    {
        $client = new ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA");
        $value = $client->getValue("nonExistingKey", false);

        $this->assertFalse($value);
    }

    public function testGetVariationId()
    {
        $client = $this->getTestClient();
        $value = $client->getVariationId("second", null);

        $this->assertEquals("fakeIdSecond", $value);
    }

    public function testGetVariationIdDefault()
    {
        $client = $this->getTestClient();
        $value = $client->getVariationId("nonexisting", null);

        $this->assertNull($value);
    }

    public function testGetAllVariationIds()
    {
        $client = $this->getTestClient();
        $value = $client->getAllVariationIds();

        $this->assertEquals(["fakeIdFirst", "fakeIdSecond"], $value);
    }

    public function testGetAllVariationIdsEmpty()
    {
        $client = new ConfigCatClient("fakeKey", ['custom-handler' => new MockHandler([
            new Response(400)
        ])]);
        $value = $client->getAllVariationIds();

        $this->assertEmpty($value);
    }

    public function testGetKeyAndValue()
    {
        $client = $this->getTestClient();
        $value = $client->getKeyAndValue("fakeIdSecond");

        $this->assertEquals("second", $value->getKey());
        $this->assertTrue($value->getValue());
    }

    public function testGetKeyAndValueNull()
    {
        $client = $this->getTestClient();
        $value = $client->getKeyAndValue("nonexisting");

        $this->assertNull($value);
    }

    private function getTestClient()
    {
        return new ConfigCatClient("fakeKey", [
            "custom-handler" => new MockHandler(
                [new Response(200, [], "{ \"first\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"second\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}")]
            ),
        ]);
    }
}
