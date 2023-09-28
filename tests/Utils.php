<?php

namespace ConfigCat\Tests;

use ConfigCat\Hooks;
use ConfigCat\Log\DefaultLogger;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use Psr\Log\NullLogger;

class Utils
{
    public static function getTestLogger(): InternalLogger
    {
        return new InternalLogger(new DefaultLogger(), LogLevel::WARNING, [], new Hooks());
    }

    public static function getNullLogger(): InternalLogger
    {
        return new InternalLogger(new NullLogger(), LogLevel::DEBUG, [], new Hooks());
    }

    public static function formatConfigWithRules(): string
    {
        return '{ "f": { "key": { "v": "def", "i": "defVar", "p": [], "r": [
            {
                "v": "fake1",
                "i": "id1",
                "t": 2,
                "a": "Identifier",
                "c": "@test1.com"
            },
            {
                "v": "fake2",
                "i": "id2",
                "t": 2,
                "a": "Identifier",
                "c": "@test2.com"
            }
        ] }}}';
    }
}
