<?php

namespace ConfigCat\Tests;

use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\Cache\ConfigEntry;
use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\EvaluationDetails;
use ConfigCat\Http\GuzzleFetchClient;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use ConfigCat\Tests\Helpers\Utils;
use ConfigCat\User;
use Exception;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;

class ConfigCatClientTest extends TestCase
{
    private const TEST_JSON = '{"f":{"first":{"t":0,"v":{"b":false},"i":"fakeIdFirst"},"second":{"t":0,"v":{"b":true},"i":"fakeIdSecond"}}}';

    public function testConstructEmptySdkKey()
    {
        $this->expectException(InvalidArgumentException::class);
        new ConfigCatClient('');
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructDefaults()
    {
        $client = new ConfigCatClient('testConstructDefaults-/1234567890123456789012');

        $logger = $this->getReflectedValue($client, 'logger');
        $this->assertInstanceOf(InternalLogger::class, $logger);

        $cache = $this->getReflectedValue($client, 'cache');
        $this->assertInstanceOf(ArrayCache::class, $cache);

        $cacheRefreshInterval = $this->getReflectedValue($client, 'cacheRefreshInterval');
        $this->assertEquals(60, $cacheRefreshInterval);
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructLoggerOption()
    {
        $logger = Utils::getNullLogger();
        $client = new ConfigCatClient('testConstructLoggerOpt/ion-567890123456789012', [
            ClientOptions::LOGGER => $logger,
            ClientOptions::LOG_LEVEL => LogLevel::ERROR,
            ClientOptions::EXCEPTIONS_TO_IGNORE => [InvalidArgumentException::class],
        ]);
        $internalLogger = $this->getReflectedValue($client, 'logger');

        $externalLogger = $this->getReflectedValue($internalLogger, 'logger');
        $globalLevel = $this->getReflectedValue($internalLogger, 'globalLevel');
        $exceptions = $this->getReflectedValue($internalLogger, 'exceptionsToIgnore');

        $this->assertSame($logger, $externalLogger);
        $this->assertSame(LogLevel::ERROR, $globalLevel);
        $this->assertTrue(in_array(InvalidArgumentException::class, $exceptions));
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructCacheOption()
    {
        $cache = new ArrayCache();
        $client = new ConfigCatClient('testConstructCacheOpti/on-4567890123456789012', [ClientOptions::CACHE => $cache]);
        $propCache = $this->getReflectedValue($client, 'cache');
        $this->assertSame($cache, $propCache);
    }

    /**
     * @throws ReflectionException
     */
    public function testConstructCacheRefreshIntervalOption()
    {
        $client = new ConfigCatClient('testConstructCacheRefr/eshIntervalOption-9012', [ClientOptions::CACHE_REFRESH_INTERVAL => 20]);
        $propInterval = $this->getReflectedValue($client, 'cacheRefreshInterval');
        $this->assertSame(20, $propInterval);
    }

    public function provideTestDataForSdkKeyFormat_ShouldBeValidated()
    {
        return Utils::withDescription([
            ['sdk-key-90123456789012', false, false],
            ['sdk-key-9012345678901/1234567890123456789012', false, false],
            ['sdk-key-90123456789012/123456789012345678901', false, false],
            ['sdk-key-90123456789012/12345678901234567890123', false, false],
            ['sdk-key-901234567890123/1234567890123456789012', false, false],
            ['sdk-key-90123456789012/1234567890123456789012', false, true],
            ['configcat-sdk-1/sdk-key-90123456789012', false, false],
            ['configcat-sdk-1/sdk-key-9012345678901/1234567890123456789012', false, false],
            ['configcat-sdk-1/sdk-key-90123456789012/123456789012345678901', false, false],
            ['configcat-sdk-1/sdk-key-90123456789012/12345678901234567890123', false, false],
            ['configcat-sdk-1/sdk-key-901234567890123/1234567890123456789012', false, false],
            ['configcat-sdk-1/sdk-key-90123456789012/1234567890123456789012', false, true],
            ['configcat-sdk-2/sdk-key-90123456789012/1234567890123456789012', false, false],
            ['configcat-proxy/', false, false],
            ['configcat-proxy/', true, false],
            ['configcat-proxy/sdk-key-90123456789012', false, false],
            ['configcat-proxy/sdk-key-90123456789012', true, true],
        ], function ($testCase) {
            return "sdkKey: {$testCase[0]} | customBaseUrl: {$testCase[1]}";
        });
    }

    /**
     * @dataProvider provideTestDataForSdkKeyFormat_ShouldBeValidated
     */
    public function testSdkKeyFormatShouldBeValidated(string $sdkKey, bool $customBaseUrl, bool $isValid)
    {
        $clientOptions = $customBaseUrl
            ? [ClientOptions::BASE_URL => 'https://my-configcat-proxy']
            : [];

        if (!$isValid) {
            $this->expectException(InvalidArgumentException::class);
        } else {
            $this->expectNotToPerformAssertions();
        }

        $client = new ConfigCatClient($sdkKey, $clientOptions);
    }

    public function testGetValueFailedFetch()
    {
        $client = new ConfigCatClient('testGetValueFailedFetc/h-34567890123456789012', [ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
            'handler' => new MockHandler(
                [new Response(400)]
            ),
        ])]);

        $value = $client->getValue('key', false);
        $this->assertFalse($value);
    }

    public function testGetAllKeysFailedFetch()
    {
        $client = new ConfigCatClient('testGetAllKeysFailedFe/tch-567890123456789012', [ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
            'handler' => new MockHandler(
                [new Response(400)]
            ),
        ])]);

        $keys = $client->getAllKeys();
        $this->assertEmpty($keys);
    }

    public function testForceRefresh()
    {
        $cache = $this->getMockBuilder(ConfigCache::class)->getMock();
        $client = new ConfigCatClient(
            'PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA',
            [ClientOptions::CACHE => $cache]
        );

        $cache
            ->expects(self::once())
            ->method('store')
        ;

        $client->forceRefresh();
    }

    public function testKeyNotExist()
    {
        $client = new ConfigCatClient('PKDVCLf-Hq-h-kCzMp-L7Q/PaDVCFk9EpmD6sLpGLltTA');
        $value = $client->getValue('nonExistingKey', false);

        $this->assertFalse($value);
    }

    public function testCacheExpiration()
    {
        $cache = $this->getMockBuilder(ConfigCache::class)->getMock();
        $mockHandler = new MockHandler(
            [new Response(200, [], self::TEST_JSON)]
        );
        $client = new ConfigCatClient('testCacheExpiration-12/1234567890123456789012', [
            ClientOptions::CACHE => $cache,
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => $mockHandler,
            ]),
            ClientOptions::CACHE_REFRESH_INTERVAL => 1,
        ]);

        $cache
            ->method('load')
            ->willReturn(ConfigEntry::fromConfigJson(self::TEST_JSON, '', \ConfigCat\Utils::getUnixMilliseconds() - 500))
        ;

        $value = $client->getValue('second', false);

        $this->assertTrue($value);
        $this->assertNull($mockHandler->getLastRequest());

        sleep(1);

        $value = $client->getValue('second', false);

        $this->assertTrue($value);
        $this->assertNotNull($mockHandler->getLastRequest());
    }

    public function testGetVariationId()
    {
        $client = new ConfigCatClient('testGetVariationId-012/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $details = $client->getValueDetails('second', false);

        $this->assertEquals('fakeIdSecond', $details->getVariationId());
    }

    public function testGetAllVariationIds()
    {
        $client = new ConfigCatClient('testGetAllVariationIds/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $value = $client->getAllValueDetails();

        $this->assertCount(2, $value);
    }

    public function testGetAllVariationIdsEmpty()
    {
        $client = new ConfigCatClient('testGetAllVariationIds/Empty-7890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(400),
        ])]);
        $value = $client->getAllValueDetails();

        $this->assertEmpty($value);
    }

    public function testGetKeyAndValue()
    {
        $client = new ConfigCatClient('testGetKeyAndValue-012/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $value = $client->getKeyAndValue('fakeIdSecond');

        $this->assertEquals('second', $value->getKey());
        $this->assertTrue($value->getValue());
    }

    public function testGetKeyAndValueNull()
    {
        $client = new ConfigCatClient('testGetKeyAndValueNull/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $value = $client->getKeyAndValue('nonexisting');

        $this->assertNull($value);
    }

    public function testGetAllValues()
    {
        $client = new ConfigCatClient('testGetAllValues-89012/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $value = $client->getAllValues();

        $this->assertEquals(['first' => false, 'second' => true], $value);
    }

    public function testGetAllValueDetails()
    {
        $client = new ConfigCatClient('testGetAllValueDetails/1234567890123456789012', [
            ClientOptions::FETCH_CLIENT => GuzzleFetchClient::create([
                'handler' => new MockHandler([new Response(200, [], self::TEST_JSON)]),
            ]),
        ]);
        $value = $client->getAllValueDetails();

        $this->assertFalse($value['first']->getValue());
        $this->assertTrue($value['second']->getValue());
    }

    public function testDefaultUser()
    {
        $client = new ConfigCatClient('testDefaultUser-789012/1234567890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules()),
        ])]);

        $user1 = new User('test@test1.com');
        $user2 = new User('test@test2.com');

        $client->setDefaultUser($user1);

        $value = $client->getValue('key', '');
        $this->assertEquals('fake1', $value);

        $value = $client->getValue('key', '', $user2);
        $this->assertEquals('fake2', $value);

        $client->clearDefaultUser();

        $value = $client->getValue('key', '');
        $this->assertEquals('def', $value);
    }

    public function testInitDefaultUser()
    {
        $client = new ConfigCatClient(
            'testInitDefaultUser-12/1234567890123456789012',
            [
                ClientOptions::CUSTOM_HANDLER => new MockHandler([new Response(200, [], Utils::formatConfigWithRules())]),
                ClientOptions::DEFAULT_USER => new User('test@test1.com'),
            ]
        );

        $user2 = new User('test@test2.com');

        $value = $client->getValue('key', '');
        $this->assertEquals('fake1', $value);

        $value = $client->getValue('key', '', $user2);
        $this->assertEquals('fake2', $value);

        $client->clearDefaultUser();

        $value = $client->getValue('key', '');
        $this->assertEquals('def', $value);
    }

    public function testDefaultUserVariationId()
    {
        $client = new ConfigCatClient('testDefaultUserVariati/onId-67890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules()),
        ])]);

        $user1 = new User('test@test1.com');
        $user2 = new User('test@test2.com');

        $client->setDefaultUser($user1);

        $value = $client->getValueDetails('key', '');
        $this->assertEquals('id1', $value->getVariationId());

        $value = $client->getValueDetails('key', '', $user2);
        $this->assertEquals('id2', $value->getVariationId());

        $client->clearDefaultUser();

        $value = $client->getValueDetails('key', '');
        $this->assertEquals('defVar', $value->getVariationId());
    }

    public function testOfflineOnline()
    {
        $handler = new MockHandler(
            [
                new Response(200, [], self::TEST_JSON),
                new Response(200, [], self::TEST_JSON),
                new Response(200, [], self::TEST_JSON),
            ]
        );

        $client = new ConfigCatClient('testOfflineOnline-9012/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => $handler,
        ]);

        $this->assertFalse($client->isOffline());
        $client->forceRefresh();

        $this->assertEquals(2, $handler->count());

        $client->setOffline();
        $client->forceRefresh();
        $client->forceRefresh();

        $this->assertEquals(2, $handler->count());
        $this->assertTrue($client->isOffline());

        $client->setOnline();
        $client->forceRefresh();

        $this->assertEquals(1, $handler->count());
        $this->assertFalse($client->isOffline());
    }

    public function testInitOfflineOnline()
    {
        $handler = new MockHandler(
            [
                new Response(200, [], self::TEST_JSON),
                new Response(200, [], self::TEST_JSON),
                new Response(200, [], self::TEST_JSON),
            ]
        );

        $client = new ConfigCatClient('testInitOfflineOnline-/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => $handler,
            ClientOptions::OFFLINE => true,
        ]);

        $this->assertTrue($client->isOffline());

        $client->forceRefresh();
        $client->forceRefresh();

        $this->assertEquals(3, $handler->count());

        $client->setOnline();
        $client->forceRefresh();

        $this->assertEquals(2, $handler->count());
        $this->assertFalse($client->isOffline());
    }

    public function testHooks()
    {
        $client = new ConfigCatClient('getTestClientWithError/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [
                    new Response(200, [], self::TEST_JSON),
                    new Response(400, [], ''),
                ]
            ),
        ]);

        $evaluated = false;
        $error = false;
        $changed = false;
        $message = '';
        $client->hooks()->addOnFlagEvaluated(function ($details) use (&$evaluated) {
            $evaluated = true;
        });
        $client->hooks()->addOnConfigChanged(function ($settings) use (&$changed) {
            $changed = true;
        });
        $client->hooks()->addOnError(function ($err) use (&$error, &$message) {
            $error = true;
            $message = $err;
        });

        $client->getValue('first', false);
        $result = $client->forceRefresh();

        $this->assertTrue($evaluated);
        $this->assertTrue($error);
        $this->assertEquals('Your SDK Key seems to be wrong. You can find the valid SDK Key at https://app.configcat.com/sdkkey. Received unexpected response: 400', $message);
        $this->assertTrue($changed);

        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Your SDK Key seems to be wrong. You can find the valid SDK Key at https://app.configcat.com/sdkkey. Received unexpected response: 400', $result->getError());
    }

    public function testEvalDetails()
    {
        $client = new ConfigCatClient('testEvalDetails-789012/1234567890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules()),
        ])]);

        $details = $client->getValueDetails('key', '', new User('test@test1.com'));

        $this->assertEquals('fake1', $details->getValue());
        $this->assertEquals('id1', $details->getVariationId());
        $this->assertNull($details->getErrorMessage());
        $this->assertEquals('key', $details->getKey());
        $this->assertEquals('test@test1.com', $details->getUser()->getIdentifier());
        $condition = $details->getMatchedTargetingRule()['c'][0]['u'];
        $this->assertEquals('Identifier', $condition['a']);
        $this->assertEquals('@test1.com', $condition['l'][0]);
        $this->assertEquals(2, $condition['c']);
        $this->assertNull($details->getMatchedPercentageOption());
        $this->assertTrue($details->getFetchTimeUnixMilliseconds() > 0);
        $this->assertFalse($details->isDefaultValue());
    }

    public function testEvalDetailsNonExistentFlag()
    {
        $client = new ConfigCatClient('testEvalDetails-789012/1234567890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules()),
        ])]);

        $details = $client->getValueDetails('non-existent', '', new User('test@test1.com'));

        $this->assertEquals('', $details->getValue());
        $this->assertEquals('', $details->getVariationId());
        $this->assertNotNull($details->getErrorMessage());
        $this->assertEquals('non-existent', $details->getKey());
        $this->assertEquals('test@test1.com', $details->getUser()->getIdentifier());
        $this->assertNull($details->getMatchedTargetingRule());
        $this->assertNull($details->getMatchedPercentageOption());
        $this->assertTrue($details->isDefaultValue());
    }

    public function testEvalDetailsHook()
    {
        $client = new ConfigCatClient('testEvalDetailsHook-12/1234567890123456789012', [ClientOptions::CUSTOM_HANDLER => new MockHandler([
            new Response(200, [], Utils::formatConfigWithRules()),
        ])]);

        $called = false;
        $client->hooks()->addOnFlagEvaluated(function (EvaluationDetails $details) use (&$called) {
            $this->assertEquals('fake1', $details->getValue());
            $this->assertEquals('id1', $details->getVariationId());
            $this->assertNull($details->getErrorMessage());
            $this->assertEquals('key', $details->getKey());
            $this->assertEquals('test@test1.com', $details->getUser()->getIdentifier());
            $condition = $details->getMatchedTargetingRule()['c'][0]['u'];
            $this->assertEquals('Identifier', $condition['a']);
            $this->assertEquals('@test1.com', $condition['l'][0]);
            $this->assertEquals(2, $condition['c']);
            $this->assertNull($details->getMatchedPercentageOption());
            $this->assertFalse($details->isDefaultValue());
            $this->assertTrue($details->getFetchTimeUnixMilliseconds() > 0);
            $called = true;
        });

        $client->getValue('key', '', new User('test@test1.com'));

        $this->assertTrue($called);
    }

    public function testTimeout()
    {
        $client = new ConfigCatClient('testTimeout-3456789012/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new ConnectException('timeout', new Request('GET', 'test')),
            ]),
        ]);
        $value = $client->getValue('test', 'def');

        $this->assertEquals('def', $value);
    }

    public function testHttpException()
    {
        $client = new ConfigCatClient('testHttpException-9012/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new RequestException('failed', new Request('GET', 'test')),
            ]),
        ]);
        $value = $client->getValue('test', 'def');

        $this->assertEquals('def', $value);
    }

    public function testGeneralException()
    {
        $client = new ConfigCatClient('testGeneralException-2/1234567890123456789012', [
            ClientOptions::CUSTOM_HANDLER => new MockHandler([
                new Exception('failed'),
            ]),
        ]);
        $value = $client->getValue('test', 'def');

        $this->assertEquals('def', $value);
    }

    /**
     * @param mixed $object
     * @param mixed $propertyName
     *
     * @throws ReflectionException
     */
    private function getReflectedValue($object, $propertyName)
    {
        $reflection = new ReflectionClass($object);
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);

        return $property->getValue($object);
    }
}
