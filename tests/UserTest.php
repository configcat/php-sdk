<?php

namespace ConfigCat\Tests;

use ConfigCat\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructNullIdentifier()
    {
        $user = new User(null);
        $this->assertEquals("", $user->getIdentifier());
    }

    public function testConstructEmptyIdentifier()
    {
        $user = new User("");
        $this->assertEquals("", $user->getIdentifier());
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetAttributeNullKey()
    {
        $user = new User("id");
        $user->getAttribute(null);
    }

    public function testGetAttributeEmptyKey()
    {
        $user = new User("id");
        $this->assertEquals("", $user->getAttribute(""));
    }
}
