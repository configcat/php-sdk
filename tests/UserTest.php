<?php

namespace ConfigCat;

use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{

    public function testConstructNullIdentifier()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new User(null);
    }

    public function testConstructEmptyIdentifier()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        new User("");
    }

    public function testGetAttributeNullKey()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $user = new User("id");
        $user->getAttribute(null);
    }

    public function testGetAttributeEmptyKey()
    {
        $this->setExpectedException(\InvalidArgumentException::class);
        $user = new User("id");
        $user->getAttribute("");
    }
}
