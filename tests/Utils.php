<?php

namespace ConfigCat\Tests;

use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;

class Utils
{
    public static function getTestLogger() {
        $handler = new ErrorLogHandler();
        $formatter = new LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);
        return new InternalLogger(new Logger("ConfigCat", [$handler]), LogLevel::WARNING, []);
    }
}