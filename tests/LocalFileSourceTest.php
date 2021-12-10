<?php

namespace ConfigCat\Tests;

use ConfigCat\ConfigCatClient;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

class LocalFileSourceTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testWithNonExistingFile() {
        new ConfigCatClient("key", [
            'file-source' => "non-existing",
        ]);
    }

    public function testWithFile() {
        $client = new ConfigCatClient("key", [
            'file-source' => "tests/test.json",
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
    }

    public function testWithSimpleFile() {
        $client = new ConfigCatClient("key", [
            'file-source' => "tests/test-simple.json",
        ]);

        $this->assertTrue($client->getValue("enabledFeature", false));
        $this->assertFalse($client->getValue("disabledFeature", true));
    }
}