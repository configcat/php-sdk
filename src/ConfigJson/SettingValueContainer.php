<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of a setting value along with related data.
 */
abstract class SettingValueContainer
{
    public const VALUE = 'v';
    public const VARIATION_ID = 'i';
}
