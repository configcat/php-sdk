<?php

namespace ConfigCat;

/**
 * Describes the location of your feature flag and setting data within the ConfigCat CDN.
 */
final class DataGovernance
{
    /** @var int Select this if your feature flags are published to all global CDN nodes. */
    public const GLOBAL_ = 0;
    /** @var int Select this if your feature flags are published to CDN nodes only in the EU. */
    public const EU_ONLY = 1;

    public static function isValid($value): bool
    {
        return self::isGlobal($value) || self::isEuOnly($value);
    }

    public static function isGlobal($value): bool
    {
        return self::GLOBAL_ == $value;
    }

    public static function isEuOnly($value): bool
    {
        return self::EU_ONLY == $value;
    }
}
