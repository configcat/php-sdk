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
use Monolog\Processor\PsrLogMessageProcessor;
use Psr\Log\LoggerInterface;

/**
 * A client for handling configurations provided by ConfigCat.
 * @package ConfigCat
 */
final class ConfigCatClient implements ClientInterface
{
    public const SDK_VERSION = '7.1.1';

    private InternalLogger $logger;
    private ConfigCache $cache;
    private ConfigFetcher $fetcher;
    private int $cacheRefreshInterval = 60;
    private string $cacheKey;
    private RolloutEvaluator $evaluator;
    private ?FlagOverrides $overrides;
    private ?User $defaultUser;
    private Hooks $hooks;
    private bool $offline = false;

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
     *     - default-user: A \ConfigCat\User as default user.
     *     - offline: Default: false. Indicates whether the SDK should be initialized in offline mode or not.
     *
     * @throws InvalidArgumentException
     *   When the $sdkKey is not valid.
     */
    public function __construct(string $sdkKey, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("'sdkKey' cannot be empty.");
        }

        $this->hooks = new Hooks();
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

        $this->logger = new InternalLogger($externalLogger, $logLevel, $exceptionsToIgnore, $this->hooks);

        $this->overrides = (isset($options[ClientOptions::FLAG_OVERRIDES]) &&
            $options[ClientOptions::FLAG_OVERRIDES] instanceof FlagOverrides)
            ? $options[ClientOptions::FLAG_OVERRIDES]
            : null;

        $this->defaultUser = (isset($options[ClientOptions::DEFAULT_USER]) &&
            $options[ClientOptions::DEFAULT_USER] instanceof User)
            ? $options[ClientOptions::DEFAULT_USER]
            : null;

        $this->cache = (isset($options[ClientOptions::CACHE]) && $options[ClientOptions::CACHE] instanceof ConfigCache)
            ? $options[ClientOptions::CACHE]
            : new ArrayCache();


        if (isset($options[ClientOptions::CACHE_REFRESH_INTERVAL]) &&
            is_int($options[ClientOptions::CACHE_REFRESH_INTERVAL])) {
            $this->cacheRefreshInterval = $options[ClientOptions::CACHE_REFRESH_INTERVAL];
        }

        $this->overrides?->setLogger($this->logger);

        if (isset($options[ClientOptions::OFFLINE]) && $options[ClientOptions::OFFLINE] === true) {
            $this->offline = true;
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
     * @param ?User $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValue(string $key, mixed $defaultValue, ?User $user = null): mixed
    {
        try {
            $settingsResult = $this->getSettingsResult();
            $errorMessage = $this->checkSettingAvailable($settingsResult, $key, '$defaultValue', $defaultValue);
            if ($errorMessage !== null) {
                $this->hooks->fireOnFlagEvaluated(EvaluationDetails::fromError(
                    $key,
                    $defaultValue,
                    $user,
                    $errorMessage
                ));
                return $defaultValue;
            }

            return $this->evaluate(
                $key,
                $settingsResult->settings[$key],
                $user,
                $settingsResult->fetchTime
            )->getValue();
        } catch (Exception $exception) {
            $message = "Error occurred in the `{METHOD_NAME}` method while evaluating setting '{KEY}'. " .
                "Returning the `{DEFAULT_PARAM_NAME}` parameter that you specified in your application: '{DEFAULT_PARAM_VALUE}'.";
            $messageCtx = [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getValue', 'KEY' => $key,
                'DEFAULT_PARAM_NAME' => '$defaultValue', 'DEFAULT_PARAM_VALUE' => Utils::getStringRepresentation($defaultValue)
            ];
            $this->logger->error($message, $messageCtx);
            $this->hooks->fireOnFlagEvaluated(EvaluationDetails::fromError(
                $key,
                $defaultValue,
                $user,
                InternalLogger::format($message, $messageCtx)
            ));
            return $defaultValue;
        }
    }

    /**
     * Gets the value and evaluation details of a feature flag or setting identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param ?User $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValueDetails(string $key, mixed $defaultValue, ?User $user = null): EvaluationDetails
    {
        try {
            $settingsResult = $this->getSettingsResult();
            $errorMessage = $this->checkSettingAvailable($settingsResult, $key, '$defaultValue', $defaultValue);
            if ($errorMessage !== null) {
                $details = EvaluationDetails::fromError(
                    $key,
                    $defaultValue,
                    $user,
                    $errorMessage
                );
                $this->hooks->fireOnFlagEvaluated($details);
                return $details;
            }

            return $this->evaluate($key, $settingsResult->settings[$key], $user, $settingsResult->fetchTime);
        } catch (Exception $exception) {
            $message = "Error occurred in the `{METHOD_NAME}` method while evaluating setting '{KEY}'. " .
                "Returning the `{DEFAULT_PARAM_NAME}` parameter that you specified in your application: '{DEFAULT_PARAM_VALUE}'.";
            $messageCtx = [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getValueDetails', 'KEY' => $key,
                'DEFAULT_PARAM_NAME' => '$defaultValue', 'DEFAULT_PARAM_VALUE' => Utils::getStringRepresentation($defaultValue)
            ];
            $this->logger->error($message, $messageCtx);
            $details = EvaluationDetails::fromError($key, $defaultValue, $user, InternalLogger::format($message, $messageCtx));
            $this->hooks->fireOnFlagEvaluated($details);
            return $details;
        }
    }

    /**
     * Gets the Variation ID (analytics) of a feature flag or setting by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultVariationId In case of any failure, this value will be returned.
     * @param ?User $user The user object to identify the caller.
     * @return ?string The Variation ID identified by the given key.
     *
     * @deprecated This method is obsolete and will be removed in a future major version.
     * Please use getValueDetails() instead.
     */
    public function getVariationId(string $key, ?string $defaultVariationId, ?User $user = null): ?string
    {
        try {
            $settingsResult = $this->getSettingsResult();
            $errorMessage = $this->checkSettingAvailable($settingsResult, $key, '$defaultVariationId', $defaultVariationId);
            if ($errorMessage !== null) {
                return $defaultVariationId;
            }

            return $this->evaluate(
                $key,
                $settingsResult->settings[$key],
                $user,
                $settingsResult->fetchTime
            )->getVariationId();
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method while evaluating setting '{KEY}'. " .
                "Returning the `{DEFAULT_PARAM_NAME}` parameter that you specified in your application: '{DEFAULT_PARAM_VALUE}'.", [
                    'event_id' => 1002, 'exception' => $exception,
                    'METHOD_NAME' => 'getVariationId', 'KEY' => $key,
                    'DEFAULT_PARAM_NAME' => '$defaultVariationId', 'DEFAULT_PARAM_VALUE' => $defaultVariationId
                ]);
            return $defaultVariationId;
        }
    }

    /**
     * Gets the Variation IDs (analytics) of all feature flags or settings.
     *
     * @param ?User $user The user object to identify the caller.
     * @return array of all Variation IDs.
     *
     * @deprecated This method is obsolete and will be removed in a future major version.
     * Please use getAllValueDetails() instead.
     */
    public function getAllVariationIds(?User $user = null): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, "empty array")) {
                return [];
            }

            return $settingsResult->settings === null ? [] : $this->parseVariationIds($settingsResult, $user);
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getAllVariationIds',
                'DEFAULT_RETURN_VALUE' => "empty array"
            ]);
            return [];
        }
    }

    /**
     * Gets the key of a setting and its value identified by the given Variation ID (analytics).
     *
     * @param string $variationId The Variation ID.
     * @return ?Pair of the key and value of a setting.
     */
    public function getKeyAndValue(string $variationId): ?Pair
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, "null")) {
                return null;
            }

            return $settingsResult->settings === null
                ? null
                : $this->parseKeyAndValue($settingsResult->settings, $variationId);
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getKeyAndValue',
                'DEFAULT_RETURN_VALUE' => "null"
            ]);
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
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, "empty array")) {
                return [];
            }

            return $settingsResult->settings === null ? [] : array_keys($settingsResult->settings);
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getAllKeys',
                'DEFAULT_RETURN_VALUE' => "empty array"
            ]);
            return [];
        }
    }

    /**
     * Gets the values of all feature flags or settings.
     *
     * @param ?User $user The user object to identify the caller.
     * @return array of values.
     */
    public function getAllValues(?User $user = null): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, "empty array")) {
                return [];
            }
            
            return $settingsResult->settings === null ? [] : $this->parseValues($settingsResult, $user);
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getAllValues',
                'DEFAULT_RETURN_VALUE' => "empty array"
            ]);
            return [];
        }
    }

    /**
     * Gets the values along with evaluation details of all feature flags and settings.
     *
     * @param ?User $user The user object to identify the caller.
     * @return EvaluationDetails[] of evaluation details of all feature flags and settings.
     */
    public function getAllValueDetails(?User $user = null): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, "empty array")) {
                return [];
            }

            $keys = array_keys($settingsResult->settings);
            $result = [];
            foreach ($keys as $key) {
                $result[$key] = $this->evaluate(
                    $key,
                    $settingsResult->settings[$key],
                    $user,
                    $settingsResult->fetchTime
                );
            }
            return $result;
        } catch (Exception $exception) {
            $this->logger->error("Error occurred in the `{METHOD_NAME}` method. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1002, 'exception' => $exception,
                'METHOD_NAME' => 'getAllValueDetails',
                'DEFAULT_RETURN_VALUE' => "empty array"
            ]);
            return [];
        }
    }

    /**
     * Initiates a force refresh on the cached configuration.
     */
    public function forceRefresh(): RefreshResult
    {
        if ($this->overrides !== null && OverrideBehaviour::LOCAL_ONLY == $this->overrides->getBehaviour()) {
            $message = "Client is configured to use the `{OVERRIDE_BEHAVIOR}` override behavior, thus `{METHOD_NAME}()` has no effect.";
            $messageCtx = [
                'event_id' => 3202,
                'OVERRIDE_BEHAVIOR' => 'LOCAL_ONLY',
                'METHOD_NAME' => 'forceRefresh'
            ];
            $this->logger->warning($message, $messageCtx);
            return new RefreshResult(false, InternalLogger::format($message, $messageCtx));
        }

        if ($this->offline) {
            $message = "Client is in offline mode, it cannot initiate HTTP calls.";
            $this->logger->warning($message, [
                'event_id' => 3200
            ]);
            return new RefreshResult(false, $message);
        }

        $cacheItem = $this->cache->load($this->cacheKey);
        if ($cacheItem === null) {
            $cacheItem = new CacheItem();
        }

        $response = $this->fetcher->fetch("");
        $this->handleResponse($response, $cacheItem);

        return new RefreshResult(!$response->isFailed(), $response->getError());
    }

    /**
     * Sets the default user.
     */
    public function setDefaultUser(User $user): void
    {
        $this->defaultUser = $user;
    }

    /**
     * Sets the default user to null.
     */
    public function clearDefaultUser(): void
    {
        $this->defaultUser = null;
    }

    /**
     * Gets the Hooks object for subscribing to SDK events.
     *
     * @return Hooks for subscribing to SDK events.
     */
    public function hooks(): Hooks
    {
        return $this->hooks;
    }

    /**
     * Configures the SDK to not initiate HTTP requests.
     */
    public function setOffline(): void
    {
        $this->offline = true;
    }

    /**
     * Configures the SDK to allow HTTP requests.
     */
    public function setOnline(): void
    {
        $this->offline = false;
    }

    /**
     * Indicates whether the SDK should be initialized in offline mode or not.
     */
    public function isOffline(): bool
    {
        return $this->offline;
    }

    private function checkSettingsAvailable(SettingsResult $settingsResult, string $defaultReturnValue): bool
    {
        if ($settingsResult->settings === null) {
            $this->logger->error("Config JSON is not present. Returning {DEFAULT_RETURN_VALUE}.", [
                'event_id' => 1000,
                'DEFAULT_RETURN_VALUE' => $defaultReturnValue
            ]);
            return false;
        }

        return true;
    }

    private function checkSettingAvailable(SettingsResult $settingsResult, string $key, string $defaultValueParam, mixed $defaultValue): ?string
    {
        if ($settingsResult->settings === null) {
            $message = "Config JSON is not present when evaluating setting '{KEY}'. " .
                "Returning the `{DEFAULT_PARAM_NAME}` parameter that you specified in your application: '{DEFAULT_PARAM_VALUE}'.";
            $messageCtx = [
                'event_id' => 1000,
                'KEY' => $key,
                'DEFAULT_PARAM_NAME' => $defaultValueParam, 'DEFAULT_PARAM_VALUE' => Utils::getStringRepresentation($defaultValue)
            ];
            $this->logger->error($message, $messageCtx);
            return InternalLogger::format($message, $messageCtx);
        }

        if (!array_key_exists($key, $settingsResult->settings)) {
            $message = "Failed to evaluate setting '{KEY}' (the key was not found in config JSON). " .
                "Returning the `{DEFAULT_PARAM_NAME}` parameter that you specified in your application: '{DEFAULT_PARAM_VALUE}'. " .
                "Available keys: [{AVAILABLE_KEYS}].";
            $messageCtx = [
                'event_id' => 1001,
                'KEY' => $key,
                'DEFAULT_PARAM_NAME' => $defaultValueParam, 'DEFAULT_PARAM_VALUE' => Utils::getStringRepresentation($defaultValue),
                'AVAILABLE_KEYS' => !empty($settingsResult->settings) ? "'".implode("', '", array_keys($settingsResult->settings))."'" : ""
            ];
            $this->logger->error($message, $messageCtx);
            return InternalLogger::format($message, $messageCtx);
        }

        return null;
    }

    private function parseValues(SettingsResult $settingsResult, User $user = null): array
    {
        $keys = array_keys($settingsResult->settings);
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->evaluate(
                $key,
                $settingsResult->settings[$key],
                $user,
                $settingsResult->fetchTime
            )->getValue();
        }

        return $result;
    }

    private function parseVariationIds(SettingsResult $settingsResult, User $user = null): array
    {
        $keys = array_keys($settingsResult->settings);
        $result = [];
        foreach ($keys as $key) {
            $result[] = $this->evaluate(
                $key,
                $settingsResult->settings[$key],
                $user,
                $settingsResult->fetchTime
            )->getVariationId();
        }

        return $result;
    }

    private function evaluate(string $key, array $setting, ?User $user, int $fetchTime): EvaluationDetails
    {
        $actualUser = $user === null ? $this->defaultUser : $user;
        $collector = new EvaluationLogCollector();
        $collector->add("Evaluating " . $key . ".");
        $result = $this->evaluator->evaluate($key, $setting, $collector, $actualUser);
        $this->logger->info("{EVALUATE_LOG}", [
            'event_id' => 5000,
            'EVALUATE_LOG' => $collector
        ]);
        $details = new EvaluationDetails(
            $key,
            $result->variationId,
            $result->value,
            $actualUser,
            false,
            null,
            $fetchTime,
            $result->targetingRule,
            $result->percentageRule
        );
        $this->hooks->fireOnFlagEvaluated($details);
        return $details;
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

        $this->logger->error("Could not find the setting for the specified variation ID: '{VARIATION_ID}'.", [
            'event_id' => 2011,
            'VARIATION_ID' => $variationId
        ]);
        return null;
    }

    private function getSettingsResult(): SettingsResult
    {
        if ($this->overrides !== null) {
            switch ($this->overrides->getBehaviour()) {
                case OverrideBehaviour::LOCAL_ONLY:
                    return new SettingsResult($this->overrides->getDataSource()->getOverrides(), 0);
                case OverrideBehaviour::LOCAL_OVER_REMOTE:
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteSettingsResult();
                    return new SettingsResult(array_merge($remote->settings ?? [], $local), $remote->fetchTime);
                default: // remote over local
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteSettingsResult();
                    return new SettingsResult(array_merge($local, $remote->settings ?? []), $remote->fetchTime);
            }
        }

        return $this->getRemoteSettingsResult();
    }

    private function getRemoteSettingsResult(): SettingsResult
    {
        $cacheItem = $this->cache->load($this->cacheKey);
        if ($cacheItem === null) {
            $cacheItem = new CacheItem();
        }

        if (!$this->offline && $cacheItem->lastRefreshed + $this->cacheRefreshInterval < time()) {
            $response = $this->fetcher->fetch($cacheItem->etag);
            $this->handleResponse($response, $cacheItem);
        }

        if (empty($cacheItem->config)) {
            return new SettingsResult(null, 0);
        }

        return new SettingsResult($cacheItem->config[Config::ENTRIES], $cacheItem->lastRefreshed);
    }

    private function handleResponse(FetchResponse $response, CacheItem $cacheItem): void
    {
        if (!$response->isFailed()) {
            if ($response->isFetched()) {
                $cacheItem->config = $response->getBody();
                $cacheItem->etag = $response->getETag();
                $this->hooks->fireOnConfigChanged($cacheItem->config[Config::ENTRIES]);
            }

            $cacheItem->lastRefreshed = time();
            $this->cache->store($this->cacheKey, $cacheItem);
        }
    }

    private function getMonolog(): Logger
    {
        $handler = new ErrorLogHandler();
        // This is a slightly modified version of the default log message format used by LineFormatter
        // (see https://github.com/Seldaek/monolog/blob/3.3.1/src/Monolog/Formatter/LineFormatter.php#L28),
        // we just add the event ID.
        $formatter = new LineFormatter(
            "[%datetime%] %channel%.%level_name%: [%context.event_id%] %message% %context% %extra%\n",
            null,
            true,
            true
        );
        $handler->setFormatter($formatter);
        // We use placeholders for message arguments as defined by the PSR-3 standard
        // (see https://www.php-fig.org/psr/psr-3/#12-message). Since `true` is passed
        // as the 2nd argument, placeholder values will be removed from the context array.
        $psrProcessor = new PsrLogMessageProcessor(null, true);
        return new Logger("ConfigCat", [$handler], [$psrProcessor]);
    }
}
