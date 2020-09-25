<?php

namespace ConfigCat;

final class DataGovernance
{
    const GLOBAL_ = 0;
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
