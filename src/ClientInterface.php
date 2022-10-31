<?php

namespace ConfigCat;

/**
 * A client for handling configurations provided by ConfigCat.
 * @package ConfigCat
 */
interface ClientInterface
{
    /**
     * Gets a value of a feature flag or setting identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValue(string $key, $defaultValue, User $user = null);

    /**
     * Gets the value and evaluation details of a feature flag or setting identified by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultValue In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
     * @return mixed The configuration value identified by the given key.
     */
    public function getValueDetails(string $key, $defaultValue, User $user = null): EvaluationDetails;

    /**
     * Gets the Variation ID (analytics) of a feature flag or setting by the given key.
     *
     * @param string $key The identifier of the configuration value.
     * @param mixed $defaultVariationId In case of any failure, this value will be returned.
     * @param User|null $user The user object to identify the caller.
     * @return mixed The Variation ID identified by the given key.
     */
    public function getVariationId(string $key, $defaultVariationId, User $user = null);

    /**
     * Gets the Variation IDs (analytics) of all feature flags or settings.
     *
     * @param User|null $user The user object to identify the caller.
     * @return array of all Variation IDs.
     */
    public function getAllVariationIds(User $user = null): array;

    /**
     * Gets the key of a setting and its value identified by the given Variation ID (analytics).
     *
     * @param string $variationId The Variation ID.
     * @return Pair|null of the key and value of a setting.
     */
    public function getKeyAndValue(string $variationId): ?Pair;

    /**
     * Gets a collection of all setting keys.
     *
     * @return array of keys.
     */
    public function getAllKeys(): array;

    /**
     * Gets the values of all feature flags or settings.
     *
     * @param User|null $user The user object to identify the caller.
     * @return array of values.
     */
    public function getAllValues(User $user = null): array;

    /**
     * Initiates a force refresh on the cached configuration.
     */
    public function forceRefresh(): void;

    /**
     * Sets the default user.
     */
    public function setDefaultUser(User $user);

    /**
     * Sets the default user to null.
     */
    public function clearDefaultUser();

    /**
     * Gets the Hooks object for subscribing to SDK events.
     *
     * @return Hooks for subscribing to SDK events.
     */
    public function hooks(): Hooks;
}
