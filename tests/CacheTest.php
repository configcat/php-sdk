<?php

namespace ConfigCat\Tests;

use ConfigCat\Cache\ConfigCache;
use ConfigCat\Cache\ConfigEntry;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Http\GuzzleFetchClient;
use DateTime;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

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

    /**
     * @dataProvider cacheKeyTestData
     *
     * @param mixed $sdkKey
     * @param mixed $cacheKey
     */
    public function testCacheKeyGeneration($sdkKey, $cacheKey)
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
