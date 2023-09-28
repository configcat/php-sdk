<?php

declare(strict_types=1);

namespace ConfigCat\Cache;

use Illuminate\Contracts\Cache\Repository;
use Psr\SimpleCache\InvalidArgumentException;

class LaravelCache extends ConfigCache
{
    public function __construct(private readonly Repository $cache) {}

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @return ?string cached value for the given key, or null if it's missing
     *
     * @throws InvalidArgumentException if the $key is not a legal value
     */
    protected function get(string $key): ?string
    {
        return $this->cache->get($key);
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     */
    protected function set(string $key, string $value): void
    {
        $this->cache->forever($key, $value);
    }
}
