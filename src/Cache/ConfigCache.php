<?php

declare(strict_types=1);

namespace ConfigCat\Cache;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * A cache API used to make custom cache implementations.
 */
abstract class ConfigCache implements LoggerAwareInterface
{
    private LoggerInterface $logger;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string      $key   identifier for the cached value
     * @param ConfigEntry $value the value to cache
     *
     * @throws invalidArgumentException
     *                                  If the $key is not a legal value
     */
    public function store(string $key, ConfigEntry $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            $this->set($key, $value->serialize());
        } catch (Exception $exception) {
            $this->logger->error('Error occurred while writing the cache.', [
                'event_id' => 2201, 'exception' => $exception,
            ]);
        }
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @throws invalidArgumentException
     *                                  If the $key is not a legal value
     *
     * @return ConfigEntry cached value for the given key, or null if it's missing
     */
    public function load(string $key): ConfigEntry
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            $cached = $this->get($key);
            if (empty($cached)) {
                return ConfigEntry::empty();
            }

            return ConfigEntry::fromCached($cached);
        } catch (Exception $exception) {
            $this->logger->error('Error occurred while reading the cache.', [
                'event_id' => 2200, 'exception' => $exception,
            ]);
        }

        return ConfigEntry::empty();
    }

    /**
     * Sets a logger instance on the object.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @return null|string cached value for the given key, or null if it's missing
     */
    abstract protected function get(string $key): ?string;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     */
    abstract protected function set(string $key, string $value): void;
}
