<?php

declare(strict_types=1);

namespace ConfigCat\Log;

use Psr\Log\LoggerInterface;

class DefaultLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        self::logWithLevel(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    /**
     * @param mixed[] $context
     */
    public static function format(string|\Stringable $message, array $context = []): string
    {
        if (array_key_exists('exception', $context)) {
            $message = $message.PHP_EOL.$context['exception']->getMessage();
        }

        return (string) $message;
    }

    /**
     * @param mixed[] $context
     */
    private static function logWithLevel(int $level, string|\Stringable $message, array $context = []): void
    {
        $date = new \DateTimeImmutable();

        $final = '['.$date->format('Y-m-d\\TH:i:sP').'] ConfigCat.'.LogLevel::asString($level).': ';

        if (array_key_exists('event_id', $context)) {
            $final = $final.'['.$context['event_id'].'] ';
        }

        error_log($final.self::format($message, $context));
    }
}
