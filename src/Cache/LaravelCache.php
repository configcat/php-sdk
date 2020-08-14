<?php

namespace ConfigCat\Cache;

use Illuminate\Contracts\Cache\Repository;
use Psr\SimpleCache\InvalidArgumentException;

class LaravelCache extends ConfigCache
{
    /** @var Repository */
    private $cache;

    public function __construct(Repository $cache)
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
    protected function get($key)
    {
        return $this->cache->get($key);
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     */
    protected function set($key, $value)
    {
        $this->cache->forever($key, $value);
    }
}
