<?php

namespace ConfigCat\Cache;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * A cache API used to make custom cache implementations.
 * @package ConfigCat
 */
abstract class ConfigCache implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     */
    abstract protected function get(string $key): ?string;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     */
    abstract protected function set(string $key, string $value): void;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param mixed $value The value to cache.
     *
     * @throws InvalidArgumentException
     *   If the $key is not a legal value.
     */
    public function store(string $key, ConfigEntry $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException("key cannot be empty.");
        }

        try {
            $this->set($key, $value->serialize());
        } catch (Exception $exception) {
            $this->logger->error("Error occurred while writing the cache.", [
                'event_id' => 2201, 'exception' => $exception
            ]);
        }
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return ConfigEntry Cached value for the given key, or null if it's missing.
     *
     * @throws InvalidArgumentException
     *   If the $key is not a legal value.
     */
    public function load(string $key): ConfigEntry
    {
        if (empty($key)) {
            throw new InvalidArgumentException("key cannot be empty.");
        }

        try {
            $cached = $this->get($key);
            if (!$cached) {
                return ConfigEntry::empty();
            }
            
            return ConfigEntry::fromCached($cached);
        } catch (Exception $exception) {
            $this->logger->error("Error occurred while reading the cache.", [
                'event_id' => 2200, 'exception' => $exception
            ]);
        }

        return ConfigEntry::empty();
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
