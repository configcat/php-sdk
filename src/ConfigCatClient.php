<?php

declare(strict_types=1);

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\PercentageAttributes;
use ConfigCat\Attributes\RolloutAttributes;
use ConfigCat\Attributes\SettingAttributes;
use ConfigCat\Cache\ArrayCache;
use ConfigCat\Cache\ConfigCache;
use ConfigCat\Cache\ConfigEntry;
use ConfigCat\Log\DefaultLogger;
use ConfigCat\Log\InternalLogger;
use ConfigCat\Log\LogLevel;
use ConfigCat\Override\FlagOverrides;
use ConfigCat\Override\OverrideBehaviour;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * A client for handling configurations provided by ConfigCat.
 */
final class ConfigCatClient implements ClientInterface
{
    public const SDK_VERSION = '8.1.0';
    private const CONFIG_JSON_CACHE_VERSION = 'v2';

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
     * Here's an example of creating a client:
     *
     *     $client = new ConfigCatClient([
     *         \ConfigCat\ClientOptions::CACHE => new \ConfigCat\Cache\LaravelCache(Cache::store()),
     *         \ConfigCat\ClientOptions::CACHE_REFRESH_INTERVAL => 5
     *     ]);
     *
     * The configuration options include the following:
     *
     * - base-url: The base ConfigCat CDN url.
     * - logger: A \Psr\Log\LoggerInterface implementation used for logging.
     * - cache: A \ConfigCat\ConfigCache implementation used for caching the latest feature flag and setting values.
     * - cache-refresh-interval: Sets how frequent the cached configuration should be refreshed in seconds.
     * - request-options: Additional options for Guzzle http requests.
     *                    https://docs.guzzlephp.org/en/stable/request-options.html
     * - custom-handler: A custom callable Guzzle http handler.
     * - fetch-client: A \ConfigCat\Http\FetchClientInterface implementation that wraps an actual HTTP client used
     *                 to make HTTP requests towards ConfigCat.
     *                 When it's not set, \ConfigCat\Http\FetchClient::Guzzle() is used by default.
     * - data-governance: Default: Global. Set this parameter to be in sync with the Data Governance
     *                    preference on the Dashboard: https://app.configcat.com/organization/data-governance
     *                    (Only Organization Admins can access)
     * - exceptions-to-ignore: Array of exception classes that should be ignored from logs.
     * - flag-overrides: A \ConfigCat\Override\FlagOverrides instance used to override
     *                   feature flags & settings.
     * - log-level: Default: Warning. Sets the internal log level.
     * - default-user: A \ConfigCat\User as default user.
     * - offline: Default: false. Indicates whether the SDK should be initialized in offline mode or not.
     *
     * All options are available on the \ConfigCat\ClientOptions class.
     *
     * @param string  $sdkKey  the SDK Key used to communicate with the ConfigCat services
     * @param mixed[] $options the configuration options
     *
     * @throws invalidArgumentException if the $sdkKey is not valid
     */
    public function __construct(string $sdkKey, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("'sdkKey' cannot be empty.");
        }

        $this->hooks = new Hooks();
        $this->cacheKey = sha1(sprintf('%s_'.ConfigFetcher::CONFIG_JSON_NAME.'_'.self::CONFIG_JSON_CACHE_VERSION, $sdkKey));

        $externalLogger = (isset($options[ClientOptions::LOGGER])
            && $options[ClientOptions::LOGGER] instanceof LoggerInterface)
            ? $options[ClientOptions::LOGGER]
            : new DefaultLogger();

        $logLevel = (isset($options[ClientOptions::LOG_LEVEL])
            && LogLevel::isValid($options[ClientOptions::LOG_LEVEL]))
            ? $options[ClientOptions::LOG_LEVEL]
            : LogLevel::WARNING;

        $exceptionsToIgnore = (isset($options[ClientOptions::EXCEPTIONS_TO_IGNORE])
            && is_array($options[ClientOptions::EXCEPTIONS_TO_IGNORE]))
            ? $options[ClientOptions::EXCEPTIONS_TO_IGNORE]
            : [];

        $this->logger = new InternalLogger($externalLogger, $logLevel, $exceptionsToIgnore, $this->hooks);

        $this->overrides = (isset($options[ClientOptions::FLAG_OVERRIDES])
            && $options[ClientOptions::FLAG_OVERRIDES] instanceof FlagOverrides)
            ? $options[ClientOptions::FLAG_OVERRIDES]
            : null;

        $this->defaultUser = (isset($options[ClientOptions::DEFAULT_USER])
            && $options[ClientOptions::DEFAULT_USER] instanceof User)
            ? $options[ClientOptions::DEFAULT_USER]
            : null;

        $this->cache = (isset($options[ClientOptions::CACHE]) && $options[ClientOptions::CACHE] instanceof ConfigCache)
            ? $options[ClientOptions::CACHE]
            : new ArrayCache();

        if (isset($options[ClientOptions::CACHE_REFRESH_INTERVAL])
            && is_int($options[ClientOptions::CACHE_REFRESH_INTERVAL])) {
            $this->cacheRefreshInterval = $options[ClientOptions::CACHE_REFRESH_INTERVAL];
        }

        $this->overrides?->setLogger($this->logger);

        if (isset($options[ClientOptions::OFFLINE]) && true === $options[ClientOptions::OFFLINE]) {
            $this->offline = true;
        }

        $this->cache->setLogger($this->logger);
        $this->fetcher = new ConfigFetcher($sdkKey, $this->logger, $options);
        $this->evaluator = new RolloutEvaluator($this->logger);
    }

    /**
     * Gets a value of a feature flag or setting identified by the given key.
     *
     * @param string $key          the identifier of the configuration value
     * @param mixed  $defaultValue in case of any failure, this value will be returned
     * @param ?User  $user         the user object to identify the caller
     *
     * @return mixed the configuration value identified by the given key
     */
    public function getValue(string $key, mixed $defaultValue, ?User $user = null): mixed
    {
        try {
            $settingsResult = $this->getSettingsResult();
            $errorMessage = $this->checkSettingAvailable($settingsResult, $key, $defaultValue);
            if (null !== $errorMessage) {
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
            $message = "Error occurred in the `getValue` method while evaluating setting '".$key."'. ".
                'Returning the `defaultValue` parameter that you specified '.
                "in your application: '".Utils::getStringRepresentation($defaultValue)."'.";
            $messageCtx = [
                'event_id' => 1002, 'exception' => $exception,
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
     * @param string $key          the identifier of the configuration value
     * @param mixed  $defaultValue in case of any failure, this value will be returned
     * @param ?User  $user         the user object to identify the caller
     *
     * @return EvaluationDetails the configuration value identified by the given key
     */
    public function getValueDetails(string $key, mixed $defaultValue, ?User $user = null): EvaluationDetails
    {
        try {
            $settingsResult = $this->getSettingsResult();
            $errorMessage = $this->checkSettingAvailable($settingsResult, $key, $defaultValue);
            if (null !== $errorMessage) {
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
            $message = "Error occurred in the `getValueDetails` method while evaluating setting '".$key."'. ".
                'Returning the `defaultValue` parameter that you specified in '.
                "your application: '".Utils::getStringRepresentation($defaultValue)."'.";
            $messageCtx = [
                'event_id' => 1002, 'exception' => $exception,
            ];
            $this->logger->error($message, $messageCtx);
            $details = EvaluationDetails::fromError($key, $defaultValue, $user, InternalLogger::format($message, $messageCtx));
            $this->hooks->fireOnFlagEvaluated($details);

            return $details;
        }
    }

    /**
     * Gets the key of a setting and its value identified by the given Variation ID (analytics).
     *
     * @param string $variationId the Variation ID
     *
     * @return ?Pair of the key and value of a setting
     */
    public function getKeyAndValue(string $variationId): ?Pair
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, 'null')) {
                return null;
            }

            return empty($settingsResult->settings)
                ? null
                : $this->parseKeyAndValue($settingsResult->settings, $variationId);
        } catch (Exception $exception) {
            $this->logger->error('Error occurred in the `getKeyAndValue` method. Returning null.', [
                'event_id' => 1002, 'exception' => $exception,
            ]);

            return null;
        }
    }

    /**
     * Gets a collection of all setting keys.
     *
     * @return string[] of keys
     */
    public function getAllKeys(): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, 'empty array')) {
                return [];
            }

            return empty($settingsResult->settings) ? [] : array_keys($settingsResult->settings);
        } catch (Exception $exception) {
            $this->logger->error('Error occurred in the `getAllKeys` method. Returning empty array.', [
                'event_id' => 1002, 'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * Gets the values of all feature flags or settings.
     *
     * @param ?User $user the user object to identify the caller
     *
     * @return mixed[] of values
     */
    public function getAllValues(?User $user = null): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, 'empty array')) {
                return [];
            }

            return empty($settingsResult->settings) ? [] : $this->parseValues($settingsResult, $user);
        } catch (Exception $exception) {
            $this->logger->error('Error occurred in the `getAllValues` method. Returning empty array.', [
                'event_id' => 1002, 'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * Gets the values along with evaluation details of all feature flags and settings.
     *
     * @param ?User $user the user object to identify the caller
     *
     * @return EvaluationDetails[] of evaluation details of all feature flags and settings
     */
    public function getAllValueDetails(?User $user = null): array
    {
        try {
            $settingsResult = $this->getSettingsResult();
            if (!$this->checkSettingsAvailable($settingsResult, 'empty array')) {
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
            $this->logger->error('Error occurred in the `getAllValueDetails` method. Returning empty array.', [
                'event_id' => 1002, 'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * Initiates a force refresh on the cached configuration.
     */
    public function forceRefresh(): RefreshResult
    {
        if (null !== $this->overrides && OverrideBehaviour::LOCAL_ONLY == $this->overrides->getBehaviour()) {
            $message = 'Client is configured to use the `LOCAL_ONLY` override behavior, thus `forceRefresh()` has no effect.';
            $messageCtx = [
                'event_id' => 3202,
            ];
            $this->logger->warning($message, $messageCtx);

            return new RefreshResult(false, InternalLogger::format($message, $messageCtx));
        }

        if ($this->offline) {
            $message = 'Client is in offline mode, it cannot initiate HTTP calls.';
            $this->logger->warning($message, [
                'event_id' => 3200,
            ]);

            return new RefreshResult(false, $message);
        }

        $cacheEntry = $this->cache->load($this->cacheKey);
        $response = $this->fetcher->fetch($cacheEntry->getEtag());
        $this->handleResponse($response, $cacheEntry);

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
     * @return Hooks for subscribing to SDK events
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
        if (empty($settingsResult->settings)) {
            $this->logger->error('Config JSON is not present. Returning '.$defaultReturnValue.'.', [
                'event_id' => 1000,
            ]);

            return false;
        }

        return true;
    }

    private function checkSettingAvailable(SettingsResult $settingsResult, string $key, mixed $defaultValue): ?string
    {
        if (!$settingsResult->hasConfigJson) {
            $message = "Config JSON is not present when evaluating setting '".$key."'. ".
                'Returning the `defaultValue` parameter that you specified in '.
                "your application: '".Utils::getStringRepresentation($defaultValue)."'.";
            $messageCtx = [
                'event_id' => 1000,
            ];
            $this->logger->error($message, $messageCtx);

            return InternalLogger::format($message, $messageCtx);
        }

        if (!array_key_exists($key, $settingsResult->settings)) {
            $message = "Failed to evaluate setting '".$key."' (the key was not found in config JSON). ".
                'Returning the `defaultValue` parameter that you specified in your '.
                "application: '".Utils::getStringRepresentation($defaultValue)."'. ".
                'Available keys: ['.(!empty($settingsResult->settings) ? "'".implode("', '", array_keys($settingsResult->settings))."'" : '').'].';
            $messageCtx = [
                'event_id' => 1001,
            ];
            $this->logger->error($message, $messageCtx);

            return InternalLogger::format($message, $messageCtx);
        }

        return null;
    }

    /**
     * @return mixed[]
     */
    private function parseValues(SettingsResult $settingsResult, User $user = null): array
    {
        if (empty($settingsResult->settings)) {
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
            )->getValue();
        }

        return $result;
    }

    /**
     * @param mixed[] $setting
     */
    private function evaluate(string $key, array $setting, ?User $user, float $fetchTime): EvaluationDetails
    {
        $actualUser = null === $user ? $this->defaultUser : $user;
        $collector = new EvaluationLogCollector();
        $collector->add('Evaluating '.$key.'.');
        $result = $this->evaluator->evaluate($key, $setting, $collector, $actualUser);
        $this->logger->info((string) $collector, [
            'event_id' => 5000,
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

    /**
     * @param array<string, mixed> $json
     */
    private function parseKeyAndValue(array $json, string $variationId): ?Pair
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

        $this->logger->error("Could not find the setting for the specified variation ID: '".$variationId."'.", [
            'event_id' => 2011,
        ]);

        return null;
    }

    private function getSettingsResult(): SettingsResult
    {
        if (null !== $this->overrides) {
            switch ($this->overrides->getBehaviour()) {
                case OverrideBehaviour::LOCAL_ONLY:
                    return new SettingsResult($this->overrides->getDataSource()->getOverrides(), 0, true);

                case OverrideBehaviour::LOCAL_OVER_REMOTE:
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteSettingsResult();

                    return new SettingsResult(array_merge($remote->settings, $local), $remote->fetchTime, $remote->hasConfigJson);

                default: // remote over local
                    $local = $this->overrides->getDataSource()->getOverrides();
                    $remote = $this->getRemoteSettingsResult();

                    return new SettingsResult(array_merge($local, $remote->settings), $remote->fetchTime, $remote->hasConfigJson);
            }
        }

        return $this->getRemoteSettingsResult();
    }

    private function getRemoteSettingsResult(): SettingsResult
    {
        $cacheEntry = $this->cache->load($this->cacheKey);
        if (!$this->offline && $cacheEntry->getFetchTime() + ($this->cacheRefreshInterval * 1000) < Utils::getUnixMilliseconds()) {
            $response = $this->fetcher->fetch($cacheEntry->getEtag());
            $cacheEntry = $this->handleResponse($response, $cacheEntry);
        }

        if (empty($cacheEntry->getConfig())) {
            return new SettingsResult([], 0, false);
        }

        return new SettingsResult($cacheEntry->getConfig()[Config::ENTRIES], $cacheEntry->getFetchTime(), true);
    }

    private function handleResponse(FetchResponse $response, ConfigEntry $cacheEntry): ConfigEntry
    {
        if ($response->isFetched()) {
            $this->hooks->fireOnConfigChanged($response->getConfigEntry()->getConfig()[Config::ENTRIES]);
            $this->cache->store($this->cacheKey, $response->getConfigEntry());

            return $response->getConfigEntry();
        }
        if ($response->isNotModified()) {
            $newEntry = $cacheEntry->withTime(Utils::getUnixMilliseconds());
            $this->cache->store($this->cacheKey, $newEntry);

            return $newEntry;
        }

        return $cacheEntry;
    }
}
