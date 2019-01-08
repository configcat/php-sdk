<?php

namespace ConfigCat;

use ConfigCat\Cache\ArrayCache;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ConfigCatClientTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNullApiKey()
    {
        new ConfigCatClient(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructEmptyApiKey()
    {
        new ConfigCatClient("");
    }

    public function testConstructDefaults()
    {
        $client = new ConfigCatClient("key");
        $this->assertAttributeInstanceOf(NullLogger::class, "logger", $client);
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
        $client = new ConfigCatClient("apiKey", ['custom-handler' => new MockHandler([
            new Response(400)
        ])]);

        $value = $client->getValue("key", false);
        $this->assertFalse($value);
    }
}
