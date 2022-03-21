<?php

namespace ConfigCat\Tests;

use ConfigCat\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    public function testConstructEmptyIdentifier()
    {
        $user = new User("");
        $this->assertEquals("", $user->getIdentifier());
    }

    public function testGetAttributeEmptyKey()
    {
        $user = new User("id");
        $this->assertEquals("", $user->getAttribute(""));
    }
}
