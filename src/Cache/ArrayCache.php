<?php

namespace ConfigCat\Cache;

/**
 * Represents a simple cache which just uses an array to store the values.
 * @package ConfigCat
 */
final class ArrayCache extends ConfigCache
{
    /** @var array */
    protected $arrayCache = [];

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     */
    protected function get(string $key): ?string
    {
        return array_key_exists($key, $this->arrayCache) ? $this->arrayCache[$key] : null;
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     */
    protected function set($key, $value): void
    {
        $this->arrayCache[$key] = $value;
    }
}
