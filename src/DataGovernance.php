<?php

namespace ConfigCat;

/**
 * Describes the location of your feature flag and setting data within the ConfigCat CDN.
 * @package ConfigCat
 */
final class DataGovernance
{
    /** @var int Select this if your feature flags are published to all global CDN nodes. */
    const GLOBAL_ = 0;
    /** @var int Select this if your feature flags are published to CDN nodes only in the EU. */
    const EU_ONLY = 1;

    public static function isValid($value)
    {
        return self::isGlobal($value) || self::isEuOnly($value);
    }

    public static function isGlobal($value)
    {
        return $value == self::GLOBAL_;
    }

    public static function isEuOnly($value)
    {
        return $value == self::EU_ONLY;
    }
}
