<?php

declare(strict_types=1);

namespace ConfigCat\Log;

use ConfigCat\Utils;
use DateTimeImmutable;
use Psr\Log\LoggerInterface;
use Stringable;

class DefaultLogger implements LoggerInterface
{
    public function emergency(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::ALERT, $message, $context);
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::CRITICAL, $message, $context);
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::ERROR, $message, $context);
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::WARNING, $message, $context);
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::NOTICE, $message, $context);
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::INFO, $message, $context);
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        self::logMsg(LogLevel::DEBUG, $message, $context);
    }

    /** @phpstan-ignore-next-line  */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    /**
     * @param mixed[] $context
     */
    private static function logMsg(int $level, string|Stringable $message, array $context = []): void
    {
        $date = new DateTimeImmutable();
        $context['timestamp'] = $date->format('Y-m-d\TH:i:sP');
        $context['level'] = LogLevel::asString($level);

        $final = self::interpolate('[{timestamp}] ConfigCat.{level}: [{event_id}] '.$message, $context);

        if (isset($context['exception'])) {
            $final .= PHP_EOL.$context['exception'];
        }

        error_log($final);
    }

    /**
     * @param mixed[] $context
     */
    private static function interpolate(string|Stringable $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            $replace['{'.$key.'}'] = Utils::getStringRepresentation($val);
        }

        return strtr((string) $message, $replace);
    }
}
