<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Setting type.
 */
enum SettingType: int
{
    /** On/off type (feature flag). */
    case BOOLEAN = 0;

    /** Text type. */
    case STRING = 1;

    /** Whole number type. */
    case INT = 2;

    /** Decimal number type. */
    case DOUBLE = 3;
}
