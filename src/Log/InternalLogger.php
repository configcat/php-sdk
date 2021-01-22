<?php

namespace ConfigCat\Log;

use Psr\Log\LoggerInterface;

/**
 * Class InternalLogger A Psr\Log\LoggerInterface for internal use only.
 * It handles the ConfigCat SDK specific log level and custom log entry filters.
 * @package ConfigCat
 */
class InternalLogger implements LoggerInterface
{
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var int
     */
    private $globalLevel;
    /**
     * @var array
     */
    private $exceptionsToIgnore;

    public function __construct(LoggerInterface $logger, $globalLevel, array $exceptionsToIgnore)
    {
        $this->logger = $logger;
        $this->globalLevel = $globalLevel;
        $this->exceptionsToIgnore = $exceptionsToIgnore;
    }

    public function emergency($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::EMERGENCY, $context)) {
            $this->logger->emergency($message, $context);
        }
    }

    public function alert($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::ALERT, $context)) {
            $this->logger->alert($message, $context);
        }
    }

    public function critical($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::CRITICAL, $context)) {
            $this->logger->critical($message, $context);
        }
    }

    public function error($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::ERROR, $context)) {
            $this->logger->error($message, $context);
        }
    }

    public function warning($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::WARNING, $context)) {
            $this->logger->warning($message, $context);
        }
    }

    public function notice($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::NOTICE, $context)) {
            $this->logger->notice($message, $context);
        }
    }

    public function info($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::INFO, $context)) {
            $this->logger->info($message, $context);
        }
    }

    public function debug($message, array $context = [])
    {
        if ($this->shouldLog(LogLevel::DEBUG, $context)) {
            $this->logger->debug($message, $context);
        }
    }

    public function log($level, $message, array $context = [])
    {
        // Do nothing, only the leveled methods should be used.
    }

    private function shouldLog($currentLevel, array $context)
    {
        if ($this->globalLevel == 0 && !$this->hasAnythingToIgnore($context)) {
            return true;
        }

        if ($currentLevel >= $this->globalLevel && !$this->hasAnythingToIgnore($context)) {
            return true;
        }

        return false;
    }

    private function hasAnythingToIgnore(array $context)
    {
        if (empty($this->exceptionsToIgnore) ||
            empty($context) ||
            !isset($context['exception'])) {
            return false;
        }

        if (in_array(get_class($context['exception']), $this->exceptionsToIgnore)) {
            return true;
        }

        return false;
    }
}
