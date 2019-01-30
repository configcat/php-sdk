<?php

namespace ConfigCat\Cache;

use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigCache A cache API used to make custom cache implementations.
 * @package ConfigCat
 */
abstract class ConfigCache implements LoggerAwareInterface
{
    /** @var LoggerInterface */
    private $logger;

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     */
    abstract protected function get($key);

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     */
    abstract protected function set($key, $value);

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param mixed $value The value to cache.
     *
     * @throws InvalidArgumentException
     *   If the $key is not a legal value.
     */
    public function store($key, CacheItem $value)
    {
        if (empty($key)) {
            throw new InvalidArgumentException("key cannot be empty.");
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
     * @param string $key Identifier for the cached value.
     * @return CacheItem|null Cached value for the given key, or null if it's missing.
     *
     * @throws InvalidArgumentException
     *   If the $key is not a legal value.
     */
    public function load($key)
    {
        if (empty($key)) {
            throw new InvalidArgumentException("key cannot be empty.");
        }

        try {
            $result = unserialize($this->get($key));
            if ($result instanceof CacheItem) {
                return $result;
            }
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return null;
        }
    }

    /**
     * Sets a logger instance on the object.
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }
}
