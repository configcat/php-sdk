<?php

namespace ConfigCat\Tests;

use ConfigCat\Cache\ConfigCache;
use ConfigCat\Cache\ConfigEntry;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Http\GuzzleFetchClient;
use DateTime;
use Exception;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class CacheTest extends TestCase
{
    private const TEST_JSON = '{"f":{"first":{"t":0,"v":{"b":false},"i":"fakeIdFirst"},"second":{"t":0,"v":{"b":true},"i":"fakeIdSecond"}}}';

    public function testCachePayload()
    {
        $testJson = '{"p":{"u":"https://cdn-global.configcat.com","r":0},"f":{"testKey":{"t":1,"v":{"s":"testValue"}}}}';

        $dateTime = new DateTime('2023-06-14T15:27:15.8440000Z');

        $time = (float) $dateTime->format('Uv');
        $etag = 'test-etag';

        $expectedPayload = "1686756435844\ntest-etag\n".$testJson;

        $entry = ConfigEntry::fromConfigJson($testJson, $etag, $time);

        $cached = $entry->serialize();

        $this->assertEquals($expectedPayload, $cached);

        $fromCache = ConfigEntry::fromCached($cached);

        $this->assertEquals($testJson, $fromCache->getConfigJson());
        $this->assertEquals($etag, $fromCache->getEtag());
        $this->assertEquals($time, $fromCache->getFetchTime());
    }

    public function testCustomCacheKeepsCurrentInMemory()
    {
        $cache = new TestCache();
        $cache->store('key', ConfigEntry::fromConfigJson(self::TEST_JSON, 'etag', 1234567));

        $cached = $cache->load('key');

        $this->assertEquals(self::TEST_JSON, $cached->getConfigJson());
        $this->assertEquals('etag', $cached->getEtag());
        $this->assertEquals(1234567, $cached->getFetchTime());
    }

    public function testThrowingCacheKeepsCurrentInMemory()
    {
        $cache = new TestCache(true);
        $cache->setLogger(new NullLogger());
        $cache->store('key', ConfigEntry::fromConfigJson(self::TEST_JSON, 'etag', 1234567));

        $cached = $cache->load('key');

        $this->assertEquals(self::TEST_JSON, $cached->getConfigJson());
        $this->assertEquals('etag', $cached->getEtag());
        $this->assertEquals(1234567, $cached->getFetchTime());
    }

    public function testCacheLoadValueFromCacheInMemory()
    {
        $secondValue = '{"f":{"testKey":{"t":1,"v":{"s":"testValue"}}}}';

        $cache = new TestCache();
        $cache->setLogger(new NullLogger());
        $cache->store('key', ConfigEntry::fromConfigJson(self::TEST_JSON, 'etag', 1234567));

        $cached = $cache->load('key');

        $this->assertEquals(self::TEST_JSON, $cached->getConfigJson());
        $this->assertEquals('etag', $cached->getEtag());
        $this->assertEquals(1234567, $cached->getFetchTime());

        $cache->setDefaultValue(ConfigEntry::fromConfigJson($secondValue, 'etag2', 12345678)->serialize());

        $cached = $cache->load('key');

        $this->assertEquals($secondValue, $cached->getConfigJson());
        $this->assertEquals('etag2', $cached->getEtag());
        $this->assertEquals(12345678, $cached->getFetchTime());

        $cache->setThrowException(true);

        $cached = $cache->load('key');

        $this->assertEquals($secondValue, $cached->getConfigJson());
        $this->assertEquals('etag2', $cached->getEtag());
        $this->assertEquals(12345678, $cached->getFetchTime());
    }

    /**
     * @dataProvider cacheKeyTestData
     */
    public function testCacheKeyGeneration(mixed $sdkKey, mixed $cacheKey)
    {
        $cache = $this->getMockBuilder(ConfigCache::class)->getMock();
        $client = new ConfigCatClient($sdkKey, [
            ClientOptions::CACHE => $cache,
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler(
                    [new Response(200, [], self::TEST_JSON)]
                ),
            ]),
        ]);

        $cache
            ->expects($this->once())
            ->method('store')
            ->with($this->equalTo($cacheKey))
        ;

        $cache
            ->expects($this->once())
            ->method('load')
            ->with($this->equalTo($cacheKey))
        ;

        $client->forceRefresh();
    }

    public function cacheKeyTestData(): array
    {
        return [
            ['configcat-sdk-1/TEST_KEY-0123456789012/1234567890123456789012', 'f83ba5d45bceb4bb704410f51b704fb6dfa19942'],
            ['configcat-sdk-1/TEST_KEY2-123456789012/1234567890123456789012', 'da7bfd8662209c8ed3f9db96daed4f8d91ba5876'],
        ];
    }
}

class TestCache extends ConfigCache
{
    private bool $throwException;

    private ?string $defaultValue;

    public function __construct($throwException = false, $defaultValue = null)
    {
        $this->throwException = $throwException;
        $this->defaultValue = $defaultValue;
    }

    public function setDefaultValue(string $value): void
    {
        $this->defaultValue = $value;
    }

    public function setThrowException(bool $throw): void
    {
        $this->throwException = $throw;
    }

    /**
     * @throws Exception
     */
    protected function get(string $key): ?string
    {
        return $this->throwException ? throw new Exception() : $this->defaultValue;
    }

    /**
     * @throws Exception
     */
    protected function set(string $key, string $value): void
    {
        if ($this->throwException) {
            throw new Exception();
        }
    }
}
