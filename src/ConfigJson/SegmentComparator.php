<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Segment comparison operator used during the evaluation process.
 */
enum SegmentComparator: int
{
    /** IS IN SEGMENT - Checks whether the conditions of the specified segment are evaluated to true. */
    case IS_IN = 0;

    /** IS NOT IN SEGMENT - Checks whether the conditions of the specified segment are evaluated to false. */
    case IS_NOT_IN = 1;
}
