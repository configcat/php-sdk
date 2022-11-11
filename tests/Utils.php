<?php

namespace ConfigCat\Tests;

use ConfigCat\Hooks;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class Utils
{
    public static function getTestLogger(): InternalLogger
    {
        $handler = new ErrorLogHandler();
        $formatter = new LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);
        return new InternalLogger(new Logger("ConfigCat", [$handler]), LogLevel::WARNING, [], new Hooks());
    }

    public static function formatConfigWithRules(): string
    {
        return "{ \"f\": { \"key\": { \"v\": \"def\", \"i\": \"defVar\", \"p\": [], \"r\": [
            {
                \"v\": \"fake1\",
                \"i\": \"id1\",
                \"t\": 2,
                \"a\": \"Identifier\",
                \"c\": \"@test1.com\"
            },
            {
                \"v\": \"fake2\",
                \"i\": \"id2\",
                \"t\": 2,
                \"a\": \"Identifier\",
                \"c\": \"@test2.com\"
            }
        ] }}}";
    }
}