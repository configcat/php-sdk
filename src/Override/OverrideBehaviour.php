<?php

namespace ConfigCat\Override;

/**
 * Class OverrideBehaviour Describes how the overrides should behave.
 * @package ConfigCat
 */
class OverrideBehaviour
{
    /**
     * With this mode, the SDK won't fetch the flags & settings from the ConfigCat CDN, and it will use only the local
     * overrides to evaluate values.
     */
    const LOCAL_ONLY = 10;
    /**
     * With this mode, the SDK will fetch the feature flags & settings from the ConfigCat CDN, and it will replace
     * those that have a matching key in the flag overrides.
     */
    const LOCAL_OVER_REMOTE = 20;
    /**
     * With this mode, the SDK will fetch the feature flags & settings from the ConfigCat CDN, and it will use the
     * overrides for only those flags that doesn't exist in the fetched configuration.
     */
    const REMOTE_OVER_LOCAL = 30;

    /**
     * Checks whether a given value is a valid behaviour.
     * @param $behaviour int The behaviour value to check.
     * @return bool True when the given value is a valid override behaviour.
     */
    public static function isValid($behaviour)
    {
        if (!is_int($behaviour)) {
            return false;
        }

        return $behaviour == self::LOCAL_ONLY ||
            $behaviour == self::LOCAL_OVER_REMOTE ||
            $behaviour == self::REMOTE_OVER_LOCAL;
    }
}
