<?php

namespace ConfigCat\Log;

/**
 * Determines the current log level of the ConfigCat SDK.
 * @package ConfigCat
 */
class LogLevel
{
    const DEBUG = 10;
    const INFO = 20;
    const NOTICE = 30;
    const WARNING = 40;
    const ERROR = 50;
    const CRITICAL = 60;
    const ALERT = 70;
    const EMERGENCY = 80;
    const NO_LOG = 90;

    public static function isValid($level): bool
    {
        if (!is_int($level)) {
            return false;
        }

        return $level == self::DEBUG ||
            $level == self::INFO ||
            $level == self::NOTICE ||
            $level == self::WARNING ||
            $level == self::ERROR ||
            $level == self::CRITICAL ||
            $level == self::ALERT ||
            $level == self::EMERGENCY;
    }
}
