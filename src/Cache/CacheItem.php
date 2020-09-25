<?php

namespace ConfigCat\Cache;

/**
 * Class CacheItem Represents the cached configuration.
 * @package ConfigCat
 */
class CacheItem
{
    /** @var int The time the cached entry refreshed. */
    public $lastRefreshed = 0;
    /** @var string The ETag. */
    public $etag;
    /** @var array The cached JSON configuration. */
    public $config;
    /** @var string The url pointing to the proper cdn server. */
    public $url;
}
