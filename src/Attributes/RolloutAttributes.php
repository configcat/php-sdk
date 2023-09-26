<?php

declare(strict_types=1);

namespace ConfigCat\Attributes;

/**
 * Represents the JSON keys of a ConfigCat roll-out rule.
 */
class RolloutAttributes
{
    public const VALUE = 'v';
    public const COMPARISON_ATTRIBUTE = 'a';
    public const COMPARATOR = 't';
    public const COMPARISON_VALUE = 'c';
    public const VARIATION_ID = 'i';
}
