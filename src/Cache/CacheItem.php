<?php

namespace ConfigCat\Cache;

/**
 * Represents the cached configuration.
 * @package ConfigCat
 */
class CacheItem
{
    /** The time the cached entry refreshed. */
    public int $lastRefreshed = 0;
    /** The ETag. */
    public ?string $etag = null;
    /** The cached JSON configuration. */
    public array $config;
    /** The url pointing to the proper cdn server. */
    public ?string $url = null;
}
