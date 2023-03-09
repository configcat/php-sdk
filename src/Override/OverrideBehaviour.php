<?php

namespace ConfigCat\Override;

/**
 * Describes how the overrides should behave.
 * @package ConfigCat
 */
class OverrideBehaviour
{
    /**
     * When evaluating values, the SDK will not use feature flags & settings from the ConfigCat CDN, but it will use
     * all feature flags & settings that are loaded from local-override sources.
     */
    public const LOCAL_ONLY = 10;
    /**
     * When evaluating values, the SDK will use all feature flags & settings that are downloaded from the ConfigCat CDN,
     * plus all feature flags & settings that are loaded from local-override sources. If a feature flag or a setting is
     * defined both in the fetched and the local-override source then the local-override version will take precedence.
     */
    public const LOCAL_OVER_REMOTE = 20;
    /**
     * When evaluating values, the SDK will use all feature flags & settings that are downloaded from the ConfigCat CDN,
     * plus all feature flags & settings that are loaded from local-override sources. If a feature flag or a setting is
     * defined both in the fetched and the local-override source then the fetched version will take precedence.
     */
    public const REMOTE_OVER_LOCAL = 30;

    /**
     * Checks whether a given value is a valid behaviour.
     * @param $behaviour int The behaviour value to check.
     * @return bool True when the given value is a valid override behaviour.
     */
    public static function isValid(int $behaviour): bool
    {
        return $behaviour == self::LOCAL_ONLY ||
            $behaviour == self::LOCAL_OVER_REMOTE ||
            $behaviour == self::REMOTE_OVER_LOCAL;
    }
}
