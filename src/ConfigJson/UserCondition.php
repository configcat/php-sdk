<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of a user condition.
 */
final class UserCondition
{
    public const COMPARISON_ATTRIBUTE = 'a';
    public const COMPARATOR = 'c';
    public const STRING_COMPARISON_VALUE = 's';
    public const NUMBER_COMPARISON_VALUE = 'd';
    public const STRINGLIST_COMPARISON_VALUE = 'l';

    private function __construct() {}
}
