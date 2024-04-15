<?php

declare(strict_types=1);

namespace ConfigCat\Cache;

use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * A cache API used to make custom cache implementations.
 */
abstract class ConfigCache implements LoggerAwareInterface
{
    /**
     * @var array<string, ConfigEntry>
     */
    private static array $inMemoryCache = [];

    private LoggerInterface $logger;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string      $key   identifier for the cached value
     * @param ConfigEntry $value the value to cache
     *
     * @throws InvalidArgumentException
     *                                  If the $key is not a legal value
     */
    public function store(string $key, ConfigEntry $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            self::$inMemoryCache[$key] = $value;
            $this->set($key, $value->serialize());
        } catch (Throwable $exception) {
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
     * @return ConfigEntry cached value for the given key, or `ConfigEntry::empty()` if it's missing
     *
     * @throws InvalidArgumentException
     *                                  If the $key is not a legal value
     */
    public function load(string $key): ConfigEntry
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            $cached = $this->get($key);
            if (empty($cached)) {
                return self::readFromMemory($key);
            }

            $fromCache = ConfigEntry::fromCached($cached);
            self::$inMemoryCache[$key] = $fromCache;

            return $fromCache;
        } catch (Throwable $exception) {
            $this->logger->error('Error occurred while reading the cache.', [
                'event_id' => 2200, 'exception' => $exception,
            ]);
        }

        return self::readFromMemory($key);
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
     * @return ?string cached value for the given key, or null if it's missing
     */
    abstract protected function get(string $key): ?string;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     */
    abstract protected function set(string $key, string $value): void;

    private function readFromMemory(string $key): ConfigEntry
    {
        return self::$inMemoryCache[$key] ?? ConfigEntry::empty();
    }
}
