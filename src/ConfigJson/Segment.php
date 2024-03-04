<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use UnexpectedValueException;

/**
 * Represents the JSON keys of a segment.
 */
abstract class Segment
{
    public const NAME = 'n';
    public const CONDITIONS = 'r';

    /**
     * @return list<array<string, mixed>>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensureList(mixed $segments): array
    {
        if (!is_array($segments) || !array_is_list($segments)) {
            throw new UnexpectedValueException('Segment list is invalid.');
        }

        return $segments;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensure(mixed $segment): array
    {
        if (!is_array($segment)) {
            throw new UnexpectedValueException('Segment is missing or invalid.');
        }

        return $segment;
    }

    /**
     * @return callable(array<string, mixed>, string&): array<string, mixed>
     */
    public static function conditionAccessor(): callable
    {
        return function (array $condition, string &$conditionType): array {
            $conditionType = ConditionContainer::USER_CONDITION;

            return $condition;
        };
    }
}
