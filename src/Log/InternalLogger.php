<?php

declare(strict_types=1);

namespace ConfigCat\Log;

use ConfigCat\Hooks;
use Psr\Log\LoggerInterface;
use Stringable;

/**
 * A Psr\Log\LoggerInterface for internal use only.
 * It handles the ConfigCat SDK specific log level and custom log entry filters.
 *
 * @internal
 */
final class InternalLogger implements LoggerInterface
{
    /**
     * @param string[] $exceptionsToIgnore
     */
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $globalLevel,
        private readonly array $exceptionsToIgnore,
        private readonly Hooks $hooks
    ) {}

    public function emergency(string|Stringable $message, array $context = []): void
    {
        $this->hooks->fireOnError(self::format($message, $context), $context['exception'] ?? null);
        if ($this->shouldLog(LogLevel::EMERGENCY, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->emergency($enriched, $context);
        }
    }

    public function alert(string|Stringable $message, array $context = []): void
    {
        $this->hooks->fireOnError(self::format($message, $context), $context['exception'] ?? null);
        if ($this->shouldLog(LogLevel::ALERT, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->alert($enriched, $context);
        }
    }

    public function critical(string|Stringable $message, array $context = []): void
    {
        $this->hooks->fireOnError(self::format($message, $context), $context['exception'] ?? null);
        if ($this->shouldLog(LogLevel::CRITICAL, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->critical($enriched, $context);
        }
    }

    public function error(string|Stringable $message, array $context = []): void
    {
        $this->hooks->fireOnError(self::format($message, $context), $context['exception'] ?? null);
        if ($this->shouldLog(LogLevel::ERROR, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->error($enriched, $context);
        }
    }

    public function warning(string|Stringable $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::WARNING, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->warning($enriched, $context);
        }
    }

    public function notice(string|Stringable $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::NOTICE, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->notice($enriched, $context);
        }
    }

    public function info(string|Stringable $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::INFO, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->info($enriched, $context);
        }
    }

    public function debug(string|Stringable $message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::DEBUG, $context)) {
            $enriched = $this->enrichMessage($message, $context);
            $this->logger->debug($enriched, $context);
        }
    }

    /** @phpstan-ignore-next-line  */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    /**
     * @param mixed[] $context
     */
    public static function format(string|Stringable $message, array $context = []): string
    {
        if (array_key_exists('exception', $context)) {
            $message = $message.PHP_EOL.$context['exception']->getMessage();
        }

        return (string) $message;
    }

    /**
     * @param mixed[] $context
     */
    public function shouldLog(int $currentLevel, array $context): bool
    {
        return $currentLevel >= $this->globalLevel && !$this->hasAnythingToIgnore($context);
    }

    /**
     * @param mixed[] $context
     */
    private function hasAnythingToIgnore(array $context): bool
    {
        if (empty($this->exceptionsToIgnore)
            || empty($context)
            || !isset($context['exception'])) {
            return false;
        }

        return in_array(get_class($context['exception']), $this->exceptionsToIgnore);
    }

    /**
     * @param mixed[] $context
     */
    private function enrichMessage(string|Stringable $message, array &$context): string|Stringable
    {
        if (!array_key_exists('event_id', $context)) {
            $context['event_id'] = 0;
        }

        return $message;
    }
}
