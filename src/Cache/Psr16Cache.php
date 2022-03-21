<?php

namespace ConfigCat\Cache;

use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;

class Psr16Cache extends ConfigCache
{
    /** @var CacheInterface */
    private $cache;

    public function __construct(CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     *
     * @throws InvalidArgumentException If the $key is not a legal value.
     */
    protected function get(string $key): ?string
    {
        return $this->cache->get($key);
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     *
     * @throws InvalidArgumentException If the $key is not a legal value.
     */
    protected function set(string $key, string $value): string
    {
        $this->cache->set($key, $value);
    }
}
