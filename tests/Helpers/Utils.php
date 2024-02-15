<?php

namespace ConfigCat\Tests\Helpers;

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
        return '{
            "f": {
              "key": {
                "t": 1,
                "r": [
                  {
                    "c": [
                      {
                        "u": {
                          "a": "Identifier",
                          "c": 2,
                          "l": [
                            "@test1.com"
                          ]
                        }
                      }
                    ],
                    "s": {
                      "v": {
                        "s": "fake1"
                      },
                      "i": "id1"
                    }
                  },
                  {
                    "c": [
                      {
                        "u": {
                          "a": "Identifier",
                          "c": 2,
                          "l": [
                            "@test2.com"
                          ]
                        }
                      }
                    ],
                    "s": {
                      "v": {
                        "s": "fake2"
                      },
                      "i": "id2"
                    }
                  }
                ],
                "v": {
                  "s": "def"
                },
                "i": "defVar"
              }
            }
          }';
    }
}
