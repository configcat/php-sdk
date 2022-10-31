<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Override\FlagOverrides;
use ConfigCat\Override\OverrideBehaviour;
use ConfigCat\Override\OverrideDataSource;
use ConfigCat\User;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class LocalSourceTest extends TestCase
{
    const TEST_JSON_BODY = "{ \"f\" : { \"disabled\": { \"v\": false, \"p\": [], \"r\": [], \"i\":\"fakeIdFirst\" }, \"enabled\": { \"v\": true, \"p\": [], \"r\": [], \"i\":\"fakeIdSecond\" }}}";

    public function testWithNonExistingFile() {
        $this->expectException(InvalidArgumentException::class);
        ConfigCatClient::get("testWithNonExistingFile", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localFile("non-existing"), OverrideBehaviour::LOCAL_ONLY),
        ]);
    }

    public function testWithInvalidBehavior() {
        $this->expectException(InvalidArgumentException::class);
        ConfigCatClient::get("testWithInvalidBehavior", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localArray([]), 50),
        ]);
    }

    public function testWithFile() {
        $client = ConfigCatClient::get("testWithFile", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localFile("tests/test.json"), OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithFile_Rules() {
        $client = ConfigCatClient::get("testWithFile_Rules", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localFile("tests/test-rules.json"), OverrideBehaviour::LOCAL_ONLY),
        ]);

        // without user
        $this->assertFalse($client->getValue("rolloutFeature", true));

        // not in rule
        $this->assertFalse($client->getValue("rolloutFeature", true, new User("test@test.com")));

        // in rule
        $this->assertTrue($client->getValue("rolloutFeature", false, new User("test@example.com")));
    }

    public function testWithSimpleFile() {
        $client = ConfigCatClient::get("testWithSimpleFile", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localFile("tests/test-simple.json"), OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithArraySource() {
        $client = ConfigCatClient::get("testWithArraySource", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localArray([
                'enabledFeature' => true,
                'disabledFeature' => false,
                'intSetting' => 5,
                'doubleSetting' => 3.14,
                'stringSetting' => "test",
            ]), OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testLocalOverRemote() {
        $client = ConfigCatClient::get("testLocalOverRemote", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localArray([
                'enabled' => false,
                'nonexisting' => true,
            ]), OverrideBehaviour::LOCAL_OVER_REMOTE),
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], self::TEST_JSON_BODY)]
            ),
        ]);

        $this->assertTrue($client->getValue("nonexisting", false));
        $this->assertFalse($client->getValue("enabled", true));
    }

    public function testRemoteOverLocal() {
        $client = ConfigCatClient::get("testRemoteOverLocal", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localArray([
                'enabled' => false,
                'nonexisting' => true,
            ]), OverrideBehaviour::REMOTE_OVER_LOCAL),
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], self::TEST_JSON_BODY)]
            ),
        ]);

        $this->assertTrue($client->getValue("nonexisting", false));
        $this->assertTrue($client->getValue("enabled", false));
    }

    public function testLocalOnlyIgnoresFetched() {
        $handler = new MockHandler(
            [new Response(200, [], self::TEST_JSON_BODY)]
        );
        $client = ConfigCatClient::get("testLocalOnlyIgnoresFetched", [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(OverrideDataSource::localArray([
                'nonexisting' => true,
            ]), OverrideBehaviour::LOCAL_ONLY),
            ClientOptions::CUSTOM_HANDLER => $handler,
        ]);

        $this->assertFalse($client->getValue("enabled", false));
        $this->assertEquals(1, $handler->count());
    }
}