<?php

namespace ConfigCat\Cache;

/**
 * Represents a simple cache which just uses a shared array to store the values.
 */
final class ArrayCache extends ConfigCache
{
    private static array $arrayCache = [];

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @return string|null cached value for the given key, or null if it's missing
     */
    protected function get(string $key): ?string
    {
        return self::$arrayCache[$key] ?? null;
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     */
    protected function set(string $key, string $value): void
    {
        self::$arrayCache[$key] = $value;
    }
}
