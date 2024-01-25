<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use UnexpectedValueException;

/**
 * Represents the JSON keys of a percentage option.
 */
abstract class PercentageOption extends SettingValueContainer
{
    public const PERCENTAGE = 'p';

    /**
     * @return list<array<string, mixed>>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensureList(mixed $percentageOptions): array
    {
        if (!is_array($percentageOptions) || !array_is_list($percentageOptions)) {
            throw new UnexpectedValueException('Percentage option list is invalid.');
        }

        return $percentageOptions;
    }
}
