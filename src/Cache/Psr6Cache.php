<?php

declare(strict_types=1);

namespace ConfigCat\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class Psr6Cache extends ConfigCache
{
    public function __construct(private readonly CacheItemPoolInterface $cachePool)
    {
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key identifier for the cached value
     *
     * @throws InvalidArgumentException if the $key is not a legal value
     *
     * @return ?string cached value for the given key, or null if it's missing
     */
    protected function get(string $key): ?string
    {
        $item = $this->cachePool->getItem($key);

        return $item->get();
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key   identifier for the cached value
     * @param string $value the value to cache
     *
     * @throws InvalidArgumentException if the $key is not a legal value
     */
    protected function set(string $key, string $value): void
    {
        $item = $this->cachePool->getItem($key);
        $item->set($value);
        $this->cachePool->save($item);
    }
}
