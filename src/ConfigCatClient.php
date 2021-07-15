<?php

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\PercentageAttributes;
use ConfigCat\Attributes\RolloutAttributes;
use ConfigCat\Attributes\SettingAttributes;
use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\CacheItem;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
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
    const SDK_VERSION = "5.3.1";

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
     * @param string $sdkKey The SDK Key used to communicate with the ConfigCat services.
     * @param array $options The configuration options:
     *     - logger: A \Psr\Log\LoggerInterface implementation used for logging.
     *     - cache: A \ConfigCat\ConfigCache implementation used for caching.
     *     - cache-refresh-interval: Sets how frequent the cached configuration should be refreshed.
     *     - request-options: Additional options for Guzzle http requests.
     *                        https://docs.guzzlephp.org/en/stable/request-options.html
     *     - custom-handler: A custom callable Guzzle http handler.
     *     - data-governance: Default: Global. Set this parameter to be in sync with the Data Governance
     *                        preference on the Dashboard: https://app.configcat.com/organization/data-governance
     *                        (Only Organization Admins can access)
     *
     * @throws InvalidArgumentException
     *   When the $sdkKey is not legal.
     */
    public function __construct($sdkKey, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("sdkKey cannot be empty.");
        }

        $this->cacheKey = sha1(sprintf("php_".ConfigFetcher::CONFIG_JSON_NAME."_%s", $sdkKey));

        $externalLogger = (isset($options['logger']) && $options['logger'] instanceof LoggerInterface)
            ? $options['logger']
            : new Logger("ConfigCat", [new ErrorLogHandler()]);

        $logLevel = (isset($options['log-level']) && LogLevel::isValid($options['log-level']))
            ? $options['log-level']
            : 0;

        $exceptionsToIgnore = (isset($options['exceptions-to-ignore']) && is_array($options['exceptions-to-ignore']))
            ? $options['exceptions-to-ignore']
            : [];

        $this->logger = new InternalLogger($externalLogger, $logLevel, $exceptionsToIgnore);

        $this->cache = (isset($options['cache']) && $options['cache'] instanceof ConfigCache)
            ? $options['cache']
            : new ArrayCache();


        if (isset($options['cache-refresh-interval']) && is_int($options['cache-refresh-interval'])) {
            $this->cacheRefreshInterval = $options['cache-refresh-interval'];
        }

        $this->cache->setLogger($this->logger);
        $this->fetcher = new ConfigFetcher($sdkKey, $this->logger, $options);
        $this->evaluator = new RolloutEvaluator($this->logger);
    }

    /**
     * Gets a value from the configuration identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
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
                    "Returning defaultValue: ". self::getStringRepresentation($defaultValue) ."." .
                    "Here are the available keys: ".implode(", ", array_keys($config)));

                return $defaultValue;
            }

            return $this->parseValue($key, $config[$key], $defaultValue, $user);
        } catch (Exception $exception) {
            $this->logger->error("Evaluating getValue('". $key ."') failed. " .
                "Returning defaultValue: ". self::getStringRepresentation($defaultValue) .". "
                . $exception->getMessage(), ['exception' => $exception]);
            return $defaultValue;
        }
    }

    /**
     * Gets the Variation ID (analytics) of a feature flag or setting by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultVariationId In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
     * @return mixed The Variation ID identified by the given key.
     */
    public function getVariationId($key, $defaultVariationId, User $user = null)
    {
        try {
            $config = $this->getConfig();
            if (empty($config)) {
                return $defaultVariationId;
            }

            if (!array_key_exists($key, $config)) {
                $this->logger->error("Evaluating getVariationId('". $key ."') failed. " .
                    "Value not found for key ". $key .". " .
                    "Returning defaultVariationId: ". $defaultVariationId .". Here are the available keys: " .
                    implode(", ", array_keys($config)));

                return $defaultVariationId;
            }

            return $this->parseVariationId($key, $config[$key], $defaultVariationId, $user);
        } catch (Exception $exception) {
            $this->logger->error("Evaluating getVariationId('". $key ."') failed. " .
                "Returning defaultVariationId: ". $defaultVariationId .". "
                . $exception->getMessage(), ['exception' => $exception]);
            return $defaultVariationId;
        }
    }

    /**
     * Gets the Variation IDs (analytics) of all feature flags or settings.
     *
     * @param User|null $user The user object to identify the caller.
     * @return array of all Variation IDs.
     */
    public function getAllVariationIds(User $user = null)
    {
        try {
            $config = $this->getConfig();
            return is_null($config) ? [] : $this->parseVariationIds($config, $user);
        } catch (Exception $exception) {
            $this->logger->error("An error occurred during getting all the variation ids. Returning empty array. "
                . $exception->getMessage(), ['exception' => $exception]);
            return [];
        }
    }

    /**
     * Gets the key of a setting and its value identified by the given Variation ID (analytics).
     *
     * @param string $variationId The Variation ID.
     * @return Pair|null of the key and value of a setting.
     */
    public function getKeyAndValue($variationId)
    {
        try {
            $config = $this->getConfig();
            return is_null($config) ? null : $this->parseKeyAndValue($config, $variationId);
        } catch (Exception $exception) {
            $this->logger->error("Could not find the setting for the given variation ID: " . $variationId . ". "
                . $exception->getMessage(), ['exception' => $exception]);
            return null;
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
            return is_null($config) ? [] : array_keys($config);
        } catch (Exception $exception) {
            $this->logger->error("An error occurred during the deserialization. Returning empty array. "
                . $exception->getMessage(), ['exception' => $exception]);
            return [];
        }
    }

    public function forceRefresh()
    {
        $cacheItem = $this->cache->load($this->cacheKey);
        if (is_null($cacheItem)) {
            $cacheItem = new CacheItem();
        }

        $response = $this->fetcher->fetch("", $cacheItem->url);
        if (!$response->isFailed()) {
            $cacheItem->lastRefreshed = time();
            $cacheItem->config = $response->getBody();
            $cacheItem->etag = $response->getETag();
            $cacheItem->url = $response->getUrl();

            $this->cache->store($this->cacheKey, $cacheItem);
        }
    }

    private function parseValue($key, array $json, $defaultValue, User $user = null)
    {
        $this->logger->info("Evaluating getValue(" . $key . ").");
        $evaluated = $this->evaluator->evaluate($key, $json, $user);
        return is_null($evaluated) ? $defaultValue : $evaluated->getValue();
    }

    private function parseVariationId($key, array $json, $defaultValue, User $user = null)
    {
        $this->logger->info("Evaluating getVariationId(" . $key . ").");
        $evaluated = $this->evaluator->evaluate($key, $json, $user);
        return is_null($evaluated) ? $defaultValue : $evaluated->getKey();
    }

    private function parseVariationIds(array $json, User $user = null)
    {
        $keys = array_keys($json);
        $result = [];
        foreach ($keys as $key) {
            $result[] = $this->parseVariationId($key, $json[$key], null, $user);
        }

        return $result;
    }

    private function parseKeyAndValue(array $json, $variationId)
    {
        foreach ($json as $key => $value) {
            if ($variationId == $value[SettingAttributes::VARIATION_ID]) {
                return new Pair($key, $value[SettingAttributes::VALUE]);
            }

            $rolloutRules = $value[SettingAttributes::ROLLOUT_RULES];
            $percentageItems = $value[SettingAttributes::ROLLOUT_PERCENTAGE_ITEMS];

            foreach ($rolloutRules as $rolloutValue) {
                if ($variationId == $rolloutValue[RolloutAttributes::VARIATION_ID]) {
                    return new Pair($key, $rolloutValue[RolloutAttributes::VALUE]);
                }
            }

            foreach ($percentageItems as $percentageValue) {
                if ($variationId == $percentageValue[PercentageAttributes::VARIATION_ID]) {
                    return new Pair($key, $percentageValue[PercentageAttributes::VALUE]);
                }
            }
        }

        $this->logger->error("Could not find the setting for the given variation ID: " . $variationId);
        return null;
    }

    /**
     * @throws ConfigCatClientException
     */
    private function getConfig()
    {
        $cacheItem = $this->cache->load($this->cacheKey);
        if (is_null($cacheItem)) {
            $cacheItem = new CacheItem();
        }

        if ($cacheItem->lastRefreshed + $this->cacheRefreshInterval < time()) {
            $response = $this->fetcher->fetch($cacheItem->etag, $cacheItem->url);

            if (!$response->isFailed()) {
                if ($response->isFetched()) {
                    $cacheItem->lastRefreshed = time();
                    $cacheItem->config = $response->getBody();
                    $cacheItem->etag = $response->getETag();
                    $cacheItem->url = $response->getUrl();
                }

                if ($response->isNotModified()) {
                    $cacheItem->lastRefreshed = time();
                }

                $this->cache->store($this->cacheKey, $cacheItem);
            }
        }

        if (empty($cacheItem->config)) {
            throw new ConfigCatClientException("Could not retrieve the config.json " .
                "from either the cache or HTTP.");
        }

        return $cacheItem->config[Config::ENTRIES];
    }

    private static function getStringRepresentation($value)
    {
        if (is_bool($value) === true) {
            return $value ? "true" : "false";
        }

        return (string)$value;
    }
}
