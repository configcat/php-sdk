<?php

namespace ConfigCat;

/**
 * A client for handling configurations provided by ConfigCat.
 */
interface ClientInterface
{
    /**
     * Gets a value of a feature flag or setting identified by the given key.
     *
     * @param string    $key          the identifier of the configuration value
     * @param mixed     $defaultValue in case of any failure, this value will be returned
     * @param User|null $user         the user object to identify the caller
     *
     * @return mixed the configuration value identified by the given key
     */
    public function getValue(string $key, mixed $defaultValue, User $user = null): mixed;

    /**
     * Gets the value and evaluation details of a feature flag or setting identified by the given key.
     *
     * @param string    $key          the identifier of the configuration value
     * @param mixed     $defaultValue in case of any failure, this value will be returned
     * @param User|null $user         the user object to identify the caller
     *
     * @return EvaluationDetails the configuration value identified by the given key
     */
    public function getValueDetails(string $key, mixed $defaultValue, User $user = null): EvaluationDetails;

    /**
     * Gets the Variation ID (analytics) of a feature flag or setting by the given key.
     *
     * @param string    $key                the identifier of the configuration value
     * @param mixed     $defaultVariationId in case of any failure, this value will be returned
     * @param User|null $user               the user object to identify the caller
     *
     * @return mixed the Variation ID identified by the given key
     */
    public function getVariationId(string $key, mixed $defaultVariationId, User $user = null): mixed;

    /**
     * Gets the Variation IDs (analytics) of all feature flags or settings.
     *
     * @param User|null $user the user object to identify the caller
     *
     * @return array of all Variation IDs
     */
    public function getAllVariationIds(User $user = null): array;

    /**
     * Gets the key of a setting and its value identified by the given Variation ID (analytics).
     *
     * @param string $variationId the Variation ID
     *
     * @return Pair|null of the key and value of a setting
     */
    public function getKeyAndValue(string $variationId): ?Pair;

    /**
     * Gets a collection of all setting keys.
     *
     * @return array of keys
     */
    public function getAllKeys(): array;

    /**
     * Gets the values of all feature flags or settings.
     *
     * @param User|null $user the user object to identify the caller
     *
     * @return array of values
     */
    public function getAllValues(User $user = null): array;

    /**
     * Initiates a force refresh on the cached configuration.
     */
    public function forceRefresh(): RefreshResult;

    /**
     * Sets the default user.
     *
     * @param User $user the default user
     */
    public function setDefaultUser(User $user): void;

    /**
     * Sets the default user to null.
     */
    public function clearDefaultUser(): void;

    /**
     * Gets the Hooks object for subscribing to SDK events.
     *
     * @return Hooks for subscribing to SDK events
     */
    public function hooks(): Hooks;

    /**
     * Configures the SDK to not initiate HTTP requests.
     */
    public function setOffline(): void;

    /**
     * Configures the SDK to allow HTTP requests.
     */
    public function setOnline(): void;

    /**
     * Indicates whether the SDK should be initialized in offline mode or not.
     */
    public function isOffline(): bool;
}
