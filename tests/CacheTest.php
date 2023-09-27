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
    private const TEST_JSON = '{ "f" : { "first": { "v": false, "p": [], "r": [], "i":"fakeIdFirst" }, "second": { "v": true, "p": [], "r": [], "i":"fakeIdSecond" }}}';

    public function testCachePayload()
    {
        $testJson = '{"p":{"u":"https://cdn-global.configcat.com","r":0},"f":{"testKey":{"v":"testValue","t":1,"p":[],"r":[]}}}';

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
            ['test1', '147c5b4c2b2d7c77e1605b1a4309f0ea6684a0c6'],
            ['test2', 'c09513b1756de9e4bc48815ec7a142b2441ed4d5'],
        ];
    }
}
