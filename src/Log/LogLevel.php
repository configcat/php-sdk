<?php

namespace ConfigCat\Log;

/**
 * Determines the current log level of the ConfigCat SDK.
 */
class LogLevel
{
    final public const DEBUG = 10;
    final public const INFO = 20;
    final public const NOTICE = 30;
    final public const WARNING = 40;
    final public const ERROR = 50;
    final public const CRITICAL = 60;
    final public const ALERT = 70;
    final public const EMERGENCY = 80;
    final public const NO_LOG = 90;

    public static function isValid($level): bool
    {
        if (!\is_int($level)) {
            return false;
        }

        return self::DEBUG == $level ||
            self::INFO == $level ||
            self::NOTICE == $level ||
            self::WARNING == $level ||
            self::ERROR == $level ||
            self::CRITICAL == $level ||
            self::ALERT == $level ||
            self::EMERGENCY == $level ||
            self::NO_LOG == $level;
    }
}
