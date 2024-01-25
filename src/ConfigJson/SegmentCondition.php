<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of a segment condition.
 */
abstract class SegmentCondition
{
    public const SEGMENT_INDEX = 's';
    public const COMPARATOR = 'c';
}
