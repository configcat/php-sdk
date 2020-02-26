<?php

namespace ConfigCat;

use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\CacheItem;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\Hash\Murmur;
use Exception;
use InvalidArgumentException;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigCatClient A client for handling configurations provided by ConfigCat.
 * @package ConfigCat
 */
final class ConfigCatClient
{
    /** @var string */
    const SDK_VERSION = "3.0.2";
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
    /** @var RolloutEvaluator */
    private $evaluator;

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
            $this->logger = new Logger("ConfigCat", [new ErrorLogHandler()]);
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
        $this->evaluator = new RolloutEvaluator($this->logger);
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
            $config = $this->getConfig();
            if (empty($config)) {
                return $defaultValue;
            }

            if (!array_key_exists($key, $config)) {
                $this->logger->error("Evaluating getValue('". $key ."') failed. " .
                    "Value not found for key ". $key .". " .
                    "Returning defaultValue: ". $defaultValue .". Here are the available keys: " .
                    implode(", ", array_keys($config)));

                return $defaultValue;
            }

            return $this->evaluate($key, $config[$key], $defaultValue, $user);
        } catch (Exception $exception) {
            $this->logger->error("Evaluating getValue('". $key ."') failed. " .
                "Returning defaultValue: ". $defaultValue .". "
                . $exception->getMessage(), ['exception' => $exception]);
            return $defaultValue;
        }
    }

    /**
     * Gets all keys from the configuration.
     *
     * @return array of keys.
     */
    public function getAllKeys()
    {
        try {
            $config = $this->getConfig();
            return array_keys($config);
        } catch (Exception $exception) {
            $this->logger->error("An error occurred during the deserialization. Returning empty array. "
                . $exception->getMessage(), ['exception' => $exception]);
            return array();
        }
    }

    public function forceRefresh()
    {
        $response = $this->fetcher->fetch("");
        if (!$response->isFailed()) {
            $cacheItem = new CacheItem();
            $cacheItem->lastRefreshed = time();
            $cacheItem->config = $response->getBody();
            $cacheItem->etag = $response->getETag();

            $this->cache->store($this->cacheKey, $cacheItem);
        }
    }

    private function evaluate($key, array $json, $defaultValue, User $user = null)
    {
        $evaluated = $this->evaluator->evaluate($key, $json, $user);
        return is_null($evaluated) ? $defaultValue : $evaluated;
    }

    private function getConfig()
    {
        $cacheItem = $this->cache->load($this->cacheKey);
        if (is_null($cacheItem)) {
            $cacheItem = new CacheItem();
        }

        if ($cacheItem->lastRefreshed + $this->cacheRefreshInterval < time()) {
            $response = $this->fetcher->fetch($cacheItem->etag);

            if (!$response->isFailed()) {
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
        }

        return $cacheItem->config;
    }
}
