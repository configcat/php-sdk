<?php

namespace ConfigCat\Log;

/**
 * Determines the current log level of the ConfigCat SDK.
 * @package ConfigCat
 */
class LogLevel
{
    public const DEBUG = 10;
    public const INFO = 20;
    public const NOTICE = 30;
    public const WARNING = 40;
    public const ERROR = 50;
    public const CRITICAL = 60;
    public const ALERT = 70;
    public const EMERGENCY = 80;
    public const NO_LOG = 90;

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
            $level == self::EMERGENCY ||
            $level == self::NO_LOG;
    }
}
