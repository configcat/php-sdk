<?php

namespace ConfigCat\Log;

use ConfigCat\Hooks;
use Psr\Log\LoggerInterface;

/**
 * A Psr\Log\LoggerInterface for internal use only.
 * It handles the ConfigCat SDK specific log level and custom log entry filters.
 *
 * @internal
 */
class InternalLogger implements LoggerInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly int $globalLevel,
        private readonly array $exceptionsToIgnore,
        private readonly Hooks $hooks
    ) {
    }

    public function emergency($message, array $context = []): void
    {
        $this->hooks->fireOnError($message);
        if ($this->shouldLog(LogLevel::EMERGENCY, $context)) {
            $this->logger->emergency($message, $context);
        }
    }

    public function alert($message, array $context = []): void
    {
        $this->hooks->fireOnError($message);
        if ($this->shouldLog(LogLevel::ALERT, $context)) {
            $this->logger->alert($message, $context);
        }
    }

    public function critical($message, array $context = []): void
    {
        $this->hooks->fireOnError($message);
        if ($this->shouldLog(LogLevel::CRITICAL, $context)) {
            $this->logger->critical($message, $context);
        }
    }

    public function error($message, array $context = []): void
    {
        $this->hooks->fireOnError($message);
        if ($this->shouldLog(LogLevel::ERROR, $context)) {
            $this->logger->error($message, $context);
        }
    }

    public function warning($message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::WARNING, $context)) {
            $this->logger->warning($message, $context);
        }
    }

    public function notice($message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::NOTICE, $context)) {
            $this->logger->notice($message, $context);
        }
    }

    public function info($message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::INFO, $context)) {
            $this->logger->info($message, $context);
        }
    }

    public function debug($message, array $context = []): void
    {
        if ($this->shouldLog(LogLevel::DEBUG, $context)) {
            $this->logger->debug($message, $context);
        }
    }

    public function log($level, $message, array $context = []): void
    {
        // Do nothing, only the leveled methods should be used.
    }

    private function shouldLog($currentLevel, array $context): bool
    {
        return $currentLevel >= $this->globalLevel && !$this->hasAnythingToIgnore($context);
    }

    private function hasAnythingToIgnore(array $context): bool
    {
        if (empty($this->exceptionsToIgnore) ||
            empty($context) ||
            !isset($context['exception'])) {
            return false;
        }

        return \in_array($context['exception']::class, $this->exceptionsToIgnore);
    }
}
