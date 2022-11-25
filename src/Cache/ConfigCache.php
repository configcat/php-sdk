<?php

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
    private ?LoggerInterface $logger = null;

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @return string|null cached value for the given key, or null if it's missing
     */
    abstract protected function get(string $key): ?string;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     */
    abstract protected function set(string $key, string $value): void;

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param mixed  $value the value to cache
     *
     * @throws invalidArgumentException
     *                                  If the $key is not a legal value
     */
    public function store(string $key, CacheItem $value): void
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            $this->set($key, serialize($value));
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @return CacheItem|null cached value for the given key, or null if it's missing
     *
     * @throws invalidArgumentException
     *                                  If the $key is not a legal value
     */
    public function load(string $key): ?CacheItem
    {
        if (empty($key)) {
            throw new InvalidArgumentException('key cannot be empty.');
        }

        try {
            $cached = $this->get($key);
            if (!$cached) {
                return null;
            }

            $result = unserialize($cached);
            if ($result instanceof CacheItem) {
                return $result;
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
        }

        return null;
    }

    /**
     * Sets a logger instance on the object.
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }
}
