<?php

namespace ConfigCat\Cache;

class Psr16Cache extends ConfigCache
{
    /** @var \Psr\SimpleCache\CacheInterface */
    private $cache;

    public function __construct(\Psr\SimpleCache\CacheInterface $cache)
    {
        $this->cache = $cache;
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function get($key)
    {
        return $this->cache->get($key);
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     */
    protected function set($key, $value)
    {
        $this->cache->set($key, $value);
    }
}
