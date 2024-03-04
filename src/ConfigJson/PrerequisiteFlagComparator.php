<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Prerequisite flag comparison operator used during the evaluation process.
 */
enum PrerequisiteFlagComparator: int
{
    /** EQUALS - It matches when the evaluated value of the specified prerequisite flag is equal to the comparison value. */
    case EQUALS = 0;

    /** NOT EQUALS - It matches when the evaluated value of the specified prerequisite flag is not equal to the comparison value. */
    case NOT_EQUALS = 1;
}
