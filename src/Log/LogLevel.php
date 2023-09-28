<?php

declare(strict_types=1);

namespace ConfigCat\Log;

/**
 * Determines the current log level of the ConfigCat SDK.
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

    public static function isValid(int $level): bool
    {
        return self::DEBUG == $level
            || self::INFO == $level
            || self::NOTICE == $level
            || self::WARNING == $level
            || self::ERROR == $level
            || self::CRITICAL == $level
            || self::ALERT == $level
            || self::EMERGENCY == $level
            || self::NO_LOG == $level;
    }

    public static function asString(int $level): string
    {
        return match ($level) {
            self::DEBUG => 'DEBUG',
            self::INFO => 'INFO',
            self::NOTICE => 'NOTICE',
            self::WARNING => 'WARNING',
            self::ERROR => 'ERROR',
            self::CRITICAL => 'CRITICAL',
            self::ALERT => 'ALERT',
            self::EMERGENCY => 'EMERGENCY',
            self::NO_LOG => 'NO_LOG',
            default => '',
        };
    }
}
