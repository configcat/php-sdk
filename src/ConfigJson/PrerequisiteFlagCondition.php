<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of a prerequisite flag condition.
 */
final class PrerequisiteFlagCondition
{
    public const PREREQUISITE_FLAG_KEY = 'f';
    public const COMPARATOR = 'c';
    public const COMPARISON_VALUE = 'v';

    private function __construct() {}
}
