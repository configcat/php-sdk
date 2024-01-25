<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use UnexpectedValueException;

/**
 * Represents the JSON keys of a setting value.
 */
abstract class SettingValue
{
    public const BOOLEAN = 'b';
    public const STRING = 's';
    public const INT = 'i';
    public const DOUBLE = 'd';

    /**
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function get(mixed $settingValue, object $type, bool $throwIfInvalid = true): null|bool|float|int|string
    {
        if (SettingType::BOOLEAN === $type) {
            $value = $settingValue[self::BOOLEAN] ?? null;
            if (is_bool($value)) {
                return $value;
            }
        } elseif (SettingType::STRING === $type) {
            $value = $settingValue[self::STRING] ?? null;
            if (is_string($value)) {
                return $value;
            }
        } elseif (SettingType::INT === $type) {
            $value = $settingValue[self::INT] ?? null;
            if (is_int($value)) {
                return $value;
            }
        } elseif (SettingType::DOUBLE === $type) {
            $value = $settingValue[self::DOUBLE] ?? null;
            if (is_double($value) || is_int($value)) {
                return (float) $value;
            }
        } else {
            if ($throwIfInvalid) {
                throw new UnexpectedValueException(null === $settingValue
                    ? 'Setting value is null.'
                    : "Setting value '{$settingValue}' is of an unsupported type (".gettype($settingValue).').');
            }

            return null;
        }

        if ($throwIfInvalid) {
            throw new UnexpectedValueException('Setting value is missing or invalid.');
        }

        return null;
    }
}
