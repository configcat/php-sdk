<?php

namespace ConfigCat;

use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\CacheItem;
use ConfigCat\Cache\ConfigCache;
use Exception;
use InvalidArgumentException;
use lastguest\Murmur;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Class ConfigCatClient A client for handling configurations provided by ConfigCat.
 * @package ConfigCat
 */
final class ConfigCatClient
{
    /** @var string */
    const SDK_VERSION = "1.1.0";
    /** @var string */
    const CACHE_KEY = "configcat-%s";

    /** @var LoggerInterface */
    private $logger;
    /** @var ConfigCache */
    private $cache;
    /** @var ConfigFetcher */
    private $fetcher;
    /** @var int */
    private $cacheRefreshInterval = 60;
    /** @var string */
    private $cacheKey;

    /**
     * Creates a new ConfigCatClient.
     *
     * @param string $apiKey The api key used to communicate with the ConfigCat services.
     * @param array $options The configuration options:
     *     - logger: a \Psr\Log\LoggerInterface implementation used for logging.
     *     - cache: a \ConfigCat\ConfigCache implementation used for caching.
     *     - cache-refresh-interval: sets how frequent the cached configuration should be refreshed.
     *     - timeout: sets the http request timeout of the underlying http requests.
     *     - connect-timeout: sets the http connect timeout.
     *     - custom-handler: a custom callable Guzzle http handler.
     *
     * @throws InvalidArgumentException
     *   When the $apiKey is not legal.
     */
    public function __construct($apiKey, array $options = [])
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException("apiKey cannot be empty.");
        }

        $hash = Murmur::hash3($apiKey);
        $this->cacheKey = sprintf(self::CACHE_KEY, $hash);

        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            $this->logger = $options['logger'];
        } else {
            $this->logger = new NullLogger();
        }

        if (isset($options['cache']) && $options['cache'] instanceof ConfigCache) {
            $this->cache = $options['cache'];
        } else {
            $this->cache = new ArrayCache();
        }

        if (isset($options['cache-refresh-interval']) && is_int($options['cache-refresh-interval'])) {
            $this->cacheRefreshInterval = $options['cache-refresh-interval'];
        }

        $this->cache->setLogger($this->logger);
        $this->fetcher = new ConfigFetcher($apiKey, $this->logger, $options);
    }

    /**
     * Gets a value from the configuration identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param User $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValue($key, $defaultValue, User $user = null)
    {
        try {
            $cacheItem = $this->cache->load($this->cacheKey);
            if (is_null($cacheItem)) {
                $cacheItem = new CacheItem();
            }

            if ($cacheItem->lastRefreshed + $this->cacheRefreshInterval < time()) {
                $response = $this->fetcher->fetch($cacheItem->etag);

                if($response->isFailed()) {
                    return $this->evaluate($key, $cacheItem->config[$key], $defaultValue, $user);
                }

                if ($response->isFetched()) {
                    $cacheItem->lastRefreshed = time();
                    $cacheItem->config = $response->getBody();
                    $cacheItem->etag = $response->getETag();
                }

                if ($response->isNotModified()) {
                    $cacheItem->lastRefreshed = time();
                }

                $this->cache->store($this->cacheKey, $cacheItem);
            }

            if (empty($cacheItem->config)) {
                return $defaultValue;
            }

            return $this->evaluate($key, $cacheItem->config[$key], $defaultValue, $user);

        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return $defaultValue;
        }
    }

    private function evaluate($key, $json, $defaultValue, $user)
    {
        $evaluated = RolloutEvaluator::evaluate($key, $json, $user);
        return is_null($evaluated) ? $defaultValue : $evaluated;
    }
}