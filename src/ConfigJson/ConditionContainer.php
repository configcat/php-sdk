<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of a condition.
 */
abstract class ConditionContainer
{
    public const USER_CONDITION = 'u';
    public const PREREQUISITE_FLAG_CONDITION = 'p';
    public const SEGMENT_CONDITION = 's';
}
