<?php

namespace ConfigCat\Cache;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

class Psr6Cache extends ConfigCache
{
    /** @var CacheItemPoolInterface */
    private $cachePool;

    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
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
        $item = $this->cachePool->getItem($key);
        return $item->get();
    }

    /**
     * Writes the value identified by the given $key into the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @param string $value The value to cache.
     *
     * @throws InvalidArgumentException If the $key is not a legal value.
     */
    protected function set(string $key, string $value): void
    {
        $item = $this->cachePool->getItem($key);
        $item->set($value);
        $this->cachePool->save($item);
    }
}
