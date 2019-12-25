<?php

namespace ConfigCat\Tests;

use ConfigCat\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructNullIdentifier()
    {
        new User(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testConstructEmptyIdentifier()
    {
        new User("");
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetAttributeNullKey()
    {
        $user = new User("id");
        $user->getAttribute(null);
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testGetAttributeEmptyKey()
    {
        $user = new User("id");
        $user->getAttribute("");
    }
}
