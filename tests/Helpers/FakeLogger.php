<?php

namespace ConfigCat\Tests\Helpers;

use ConfigCat\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Stringable;

class FakeLogger implements LoggerInterface
{
    public array $events = [];

    public static function formatMessage(array $event)
    {
        $context = $event['context'];
        $context['level'] = LogLevel::asString($event['level']);

        $final = self::interpolate('{level} '.$event['message'], $context);

        if (isset($context['exception'])) {
            $final .= PHP_EOL.$context['exception'];
        }

        return $final;
    }

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

    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    /**
     * @param mixed[] $context
     */
    private function logMsg(int $level, string|Stringable $message, array $context = []): void
    {
        $this->events[] = [
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ];
    }

    /**
     * @param mixed[] $context
     */
    private static function interpolate(string|Stringable $message, array $context = []): string
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
