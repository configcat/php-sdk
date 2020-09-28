<?php

namespace ConfigCat;

/** Class DataGovernance describes the location of your feature flag and setting data within the ConfigCat CDN. */
final class DataGovernance
{
    /** @var int GLOBAL_ means your data will be published to all ConfigCat CDN nodes to guarantee lowest response times. */
    const GLOBAL_ = 0;
    /** @var int EU_ONLY means your data will be published to CDN nodes only in the EU. */
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
