<?php

namespace ConfigCat\Cache;

class Psr6Cache extends ConfigCache
{
    /** @var \Psr\Cache\CacheItemPoolInterface */
    private $cachePool;

    public function __construct(\Psr\Cache\CacheItemPoolInterface $cachePool)
    {
        $this->cachePool = $cachePool;
    }

    /**
     * Reads the value identified by the given $key from the underlying cache.
     *
     * @param string $key Identifier for the cached value.
     * @return string|null Cached value for the given key, or null if it's missing.
     *
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function get($key)
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
     * @throws \Psr\Cache\InvalidArgumentException
     */
    protected function set($key, $value)
    {
        $item = $this->cachePool->getItem($key);
        $item->set($value);
        $this->cachePool->save($item);
    }
}