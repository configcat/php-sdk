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
use ConfigCat\Override\FlagOverrides;
use ConfigCat\Override\OverrideBehaviour;
use Exception;
use InvalidArgumentException;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

/**
 * A client for handling configurations provided by ConfigCat.
 * @package ConfigCat
 */
final class ConfigCatClient implements ClientInterface
{
    /** @var string */
    const SDK_VERSION = "6.1.0";

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
    /** @var FlagOverrides */
    private $overrides;

    /**
     * Creates a new ConfigCatClient.
     *
     * @param string $sdkKey The SDK Key used to communicate with the ConfigCat services.
     * @param array $options The configuration options:
     *     - base-url: The base ConfigCat CDN url.
     *     - logger: A \Psr\Log\LoggerInterface implementation used for logging.
     *     - cache: A \ConfigCat\ConfigCache implementation used for caching the latest feature flag and setting values.
     *     - cache-refresh-interval: Sets how frequent the cached configuration should be refreshed.
     *     - request-options: Additional options for Guzzle http requests.
     *                        https://docs.guzzlephp.org/en/stable/request-options.html
     *     - custom-handler: A custom callable Guzzle http handler.
     *     - data-governance: Default: Global. Set this parameter to be in sync with the Data Governance
     *                        preference on the Dashboard: https://app.configcat.com/organization/data-governance
     *                        (Only Organization Admins can access)
     *     - exceptions-to-ignore: Array of exception classes that should be ignored from logs.
     *     - flag-overrides: A \ConfigCat\Override\FlagOverrides instance used to override
     *                       feature flags & settings.
     *     - log-level: Default: Warning. Sets the internal log level.
     *
     * @throws InvalidArgumentException
     *   When the $sdkKey is not valid.
     */
    public function __construct(string $sdkKey, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("'sdkKey' cannot be empty.");
        }

        $this->cacheKey = sha1(sprintf("php_" . ConfigFetcher::CONFIG_JSON_NAME . "_%s", $sdkKey));

        $externalLogger = (isset($options[ClientOptions::LOGGER]) &&
            $options[ClientOptions::LOGGER] instanceof LoggerInterface)
            ? $options[ClientOptions::LOGGER]
            : $this->getMonolog();

        $logLevel = (isset($options[ClientOptions::LOG_LEVEL]) &&
            LogLevel::isValid($options[ClientOptions::LOG_LEVEL]))
            ? $options[ClientOptions::LOG_LEVEL]
            : LogLevel::WARNING;

        $exceptionsToIgnore = (isset($options[ClientOptions::EXCEPTIONS_TO_IGNORE]) &&
            is_array($options[ClientOptions::EXCEPTIONS_TO_IGNORE]))
            ? $options[ClientOptions::EXCEPTIONS_TO_IGNORE]
            : [];

        $this->logger = new InternalLogger($externalLogger, $logLevel, $exceptionsToIgnore);

        $this->overrides = (isset($options[ClientOptions::FLAG_OVERRIDES]) &&
            $options[ClientOptions::FLAG_OVERRIDES] instanceof FlagOverrides)
            ? $options[ClientOptions::FLAG_OVERRIDES]
            : null;

        $this->cache = (isset($options[ClientOptions::CACHE]) && $options[ClientOptions::CACHE] instanceof ConfigCache)
            ? $options[ClientOptions::CACHE]
            : new ArrayCache();


        if (isset($options[ClientOptions::CACHE_REFRESH_INTERVAL]) &&
            is_int($options[ClientOptions::CACHE_REFRESH_INTERVAL])) {
            $this->cacheRefreshInterval = $options[ClientOptions::CACHE_REFRESH_INTERVAL];
        }

        if (!is_null($this->overrides)) {
            $this->overrides->setLogger($this->logger);
        }

        $this->cache->setLogger($this->logger);
        $this->fetcher = new ConfigFetcher($sdkKey, $this->logger, $options);
        $this->evaluator = new RolloutEvaluator($this->logger);
    }

    /**
     * Gets a value of a feature flag or setting identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValue(string $key, $defaultValue, User $user = null)
    {
        try {
            $config = $this->getConfig();
            if (empty($config)) {
                return $defaultValue;
            }

            if (!array_key_exists($key, $config)) {
                $this->logger->error("Evaluating getValue('" . $key . "') failed. " .
                    "Value not found for key " . $key . ". " .
                    "Returning defaultValue: " . Utils::getStringRepresentation($defaultValue) . ". " .
                    "Here are the available keys: " . implode(", ", array_keys($config)));

                return $defaultValue;
            }

            return $this->parseValue($key, $config[$key], $defaultValue, $user);
        } catch (Exception $exception) {
            $this->logger->error("Evaluating getValue('" . $key . "') failed. " .
                "Returning defaultValue: " . Utils::getStringRepresentation($defaultValue) . ". "
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
    public function getVariationId(string $key, $defaultVariationId, User $user = null)
    {
        try {
            $config = $this->getConfig();
            if (empty($config)) {
                return $defaultVariationId;
            }

            if (!array_key_exists($key, $config)) {
                $this->logger->error("Evaluating getVariationId('" . $key . "') failed. " .
                    "Value not found for key " . $key . ". " .
                    "Returning defaultVariationId: " . $defaultVariationId . ". Here are the available keys: " .
                    implode(", ", array_keys($config)));

                return $defaultVariationId;
            }

            return $this->parseVariationId($key, $config[$key], $defaultVariationId, $user);
        } catch (Exception $exception) {
            $this->logger->error("Evaluating getVariationId('" . $key . "') failed. " .
                "Returning defaultVariationId: " . $defaultVariationId . ". "
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
    public function getAllVariationIds(User $user = null): array
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
    public function getKeyAndValue(string $variationId): ?Pair
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
     * Gets a collection of all setting keys.
     *
     * @return array of keys.
     */
    public function getAllKeys(): array
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

    /**
     * Gets the values of all feature flags or settings.
     *
     * @param User|null $user The user object to identify the caller.
     * @return array of values.
     */
    public function getAllValues(User $user = null): array
    {
        try {
            $config = $this->getConfig();
            return is_null($config) ? [] : $this->parseValues($config, $user);
        } catch (Exception $exception) {
            $this->logger->error("An error occurred during getting all values. Returning empty array. "
                . $exception->getMessage(), ['exception' => $exception]);
            return [];
        }
    }

    /**
     * Initiates a force refresh on the cached configuration.
     */
    public function forceRefresh(): void
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

    private function parseValue(string $key, array $json, $defaultValue, User $user = null)
    {
        $collector = new EvaluationLogCollector();
        $collector->add("Evaluating getValue(" . $key . ").");
        $evaluated = $this->evaluator->evaluate($key, $json, $collector, $user);
        $this->logger->info($collector);
        return is_null($evaluated) ? $defaultValue : $evaluated->getValue();
    }

    private function parseVariationId($key, array $json, $defaultVariationId, User $user = null): string
    {
        $collector = new EvaluationLogCollector();
        $collector->add("Evaluating getVariationId(" . $key . ").");
        $evaluated = $this->evaluator->evaluate($key, $json, $collector, $user);
        $this->logger->info($collector);
        return is_null($evaluated) ? $defaultVariationId : $evaluated->getKey();
    }

    private function parseValues(array $json, User $user = null): array
    {
        $keys = array_keys($json);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->parseValue($key, $json[$key], null, $user);
        }

        return $result;
    }

    private function parseVariationIds(array $json, User $user = null): array
    {
        $keys = array_keys($json);
        $result = [];
        foreach ($keys as $key) {
            $result[] = $this->parseVariationId($key, $json[$key], null, $user);
        }

        return $result;
    }

    private function parseKeyAndValue(array $json, $variationId): ?Pair
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
        if (!is_null($this->overrides)) {
            switch ($this->overrides->getBehaviour()) {
                case OverrideBehaviour::LOCAL_ONLY:
                    return $this->overrides->getDataSource()->getOverrides();
                case OverrideBehaviour::LOCAL_OVER_REMOTE:
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteConfig();
                    return array_merge($remote, $local);
                case OverrideBehaviour::REMOTE_OVER_LOCAL:
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteConfig();
                    return array_merge($local, $remote);
                default:
                    throw new InvalidArgumentException("Invalid override behaviour.");
            }
        }

        return $this->getRemoteConfig();
    }

    /**
     * @throws ConfigCatClientException
     */
    private function getRemoteConfig()
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

    private function getMonolog(): Logger
    {
        $handler = new ErrorLogHandler();
        $formatter = new LineFormatter(null, null, true, true);
        $handler->setFormatter($formatter);
        return new Logger("ConfigCat", [$handler]);
    }
}
