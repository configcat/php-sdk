<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use UnexpectedValueException;

/**
 * Represents the JSON keys of a targeting rule.
 */
abstract class TargetingRule
{
    public const CONDITIONS = 'c';
    public const SIMPLE_VALUE = 's';
    public const PERCENTAGE_OPTIONS = 'p';

    /**
     * @return list<array<string, mixed>>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensureList(mixed $targetingRules): array
    {
        if (!is_array($targetingRules) || !array_is_list($targetingRules)) {
            throw new UnexpectedValueException('Targeting rule list is invalid.');
        }

        return $targetingRules;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensure(mixed $targetingRule): array
    {
        if (!is_array($targetingRule)) {
            throw new UnexpectedValueException('Targeting rule is missing or invalid.');
        }

        return $targetingRule;
    }

    /**
     * @param array<string, mixed> $targetingRule
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function hasPercentageOptions(array $targetingRule): bool
    {
        $simpleValue = $targetingRule[self::SIMPLE_VALUE] ?? null;
        $percentageOptions = $targetingRule[self::PERCENTAGE_OPTIONS] ?? null;

        if (isset($simpleValue)) {
            if (!isset($percentageOptions) && is_array($simpleValue)) {
                return false;
            }
        } elseif (is_array($percentageOptions) && array_is_list($percentageOptions)) {
            return true;
        }

        throw new UnexpectedValueException('Targeting rule THEN part is missing or invalid.');
    }
}
