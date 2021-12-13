<?php

namespace ConfigCat\Tests;

use ConfigCat\ConfigCatClient;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class LocalSourceTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithNonExistingFile() {
        new ConfigCatClient("key", [
            'file-source' => "non-existing",
        ]);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithNonArray() {
        new ConfigCatClient("key", [
            'array-source' => "non-existing",
        ]);
    }

    public function testWithFile() {
        $client = new ConfigCatClient("key", [
            'file-source' => "tests/test.json",
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithSimpleFile() {
        $client = new ConfigCatClient("key", [
            'file-source' => "tests/test-simple.json",
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }

    public function testWithArraySource() {
        $client = new ConfigCatClient("key", [
            'array-source' => [
                'enabledFeature' => true,
                'disabledFeature' => false,
                'intSetting' => 5,
                'doubleSetting' => 3.14,
                'stringSetting' => "test",
            ],
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
        $this->assertEquals(5, $client->getValue("intSetting", 0));
        $this->assertEquals(3.14, $client->getValue("doubleSetting", 0.0));
        $this->assertEquals("test", $client->getValue("stringSetting", 0));
    }
}