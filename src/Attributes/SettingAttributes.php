<?php

declare(strict_types=1);

namespace ConfigCat\Attributes;

/**
 * Represents the JSON keys of a ConfigCat setting.
 */
class SettingAttributes
{
    public const VALUE = 'v';
    public const TYPE = 't';
    public const ROLLOUT_PERCENTAGE_ITEMS = 'p';
    public const ROLLOUT_RULES = 'r';
    public const VARIATION_ID = 'i';
}
