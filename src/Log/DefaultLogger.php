<?php

declare(strict_types=1);

namespace ConfigCat\Log;

use Psr\Log\LoggerInterface;

class DefaultLogger implements LoggerInterface
{
    public function emergency($message, array $context = []): void
    {
        self::logMsg(LogLevel::EMERGENCY, $message, $context);
    }

    public function alert($message, array $context = []): void
    {
        self::logMsg(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        self::logMsg(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        self::logMsg(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        self::logMsg(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        self::logMsg(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        self::logMsg(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        self::logMsg(LogLevel::DEBUG, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    /**
     * @param mixed[] $context
     */
    private static function logMsg(int $level, string|\Stringable $message, array $context = []): void
    {
        $date = new \DateTimeImmutable();
        $context['timestamp'] = $date->format('Y-m-d\\TH:i:sP');
        $context['level'] = LogLevel::asString($level);

        $final = '[{timestamp}] ConfigCat.{level}: '.$message;

        error_log(self::interpolate($final, $context));
    }

    /**
     * @param mixed[] $context
     */
    private static function interpolate(string|\Stringable $message, array $context = []): string
    {
        $replace = [];
        foreach ($context as $key => $val) {
            if (!is_array($val) && (!is_object($val) || method_exists($val, '__toString'))) {
                $replace['{'.$key.'}'] = $val;
            }
        }

        return strtr((string) $message, $replace);
    }
}
