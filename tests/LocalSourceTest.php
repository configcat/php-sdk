<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
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

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithNonExistingFile() {
        new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localFile("non-existing", OverrideBehaviour::LOCAL_ONLY),
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithNonArray() {
        new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localArray(null, OverrideBehaviour::LOCAL_ONLY),
        ]);
    }

    public function testWithFile() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localFile("tests/test.json", OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithFile_Rules() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localFile("tests/test-rules.json", OverrideBehaviour::LOCAL_ONLY),
        ]);

        // without user
        $this->assertFalse($client->getValue("rolloutFeature", true));

        // not in rule
        $this->assertFalse($client->getValue("rolloutFeature", true, new User("test@test.com")));

        // in rule
        $this->assertTrue($client->getValue("rolloutFeature", false, new User("test@example.com")));
    }

    public function testWithSimpleFile() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localFile("tests/test-simple.json", OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithArraySource() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localArray([
                'enabledFeature' => true,
                'disabledFeature' => false,
                'intSetting' => 5,
                'doubleSetting' => 3.14,
                'stringSetting' => "test",
            ], OverrideBehaviour::LOCAL_ONLY),
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testLocalOverRemote() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localArray([
                'enabled' => false,
                'nonexisting' => true,
            ], OverrideBehaviour::LOCAL_OVER_REMOTE),
            ClientOptions::CUSTOM_HANDLER => new MockHandler(
                [new Response(200, [], self::TEST_JSON_BODY)]
            ),
        ]);

        $this->assertTrue($client->getValue("nonexisting", false));
        $this->assertFalse($client->getValue("enabled", true));
    }

    public function testRemoteOverLocal() {
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localArray([
                'enabled' => false,
                'nonexisting' => true,
            ], OverrideBehaviour::REMOTE_OVER_LOCAL),
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
        $client = new ConfigCatClient("key", [
            ClientOptions::FLAG_OVERRIDES => OverrideDataSource::localArray([
                'nonexisting' => true,
            ], OverrideBehaviour::LOCAL_ONLY),
            ClientOptions::CUSTOM_HANDLER => $handler,
        ]);

        $this->assertFalse($client->getValue("enabled", false));
        $this->assertEquals(1, $handler->count());
    }
}