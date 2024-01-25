<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use stdClass;
use UnexpectedValueException;

/**
 * Represents the JSON keys of a setting.
 */
abstract class Setting extends SettingValueContainer
{
    public const TYPE = 't';
    public const PERCENTAGE_OPTIONS_ATTRIBUTE = 'a';
    public const TARGETING_RULES = 'r';
    public const PERCENTAGE_OPTIONS = 'p';

    /**
     * @internal
     */
    public const CONFIG_JSON_SALT = '__configJsonSalt';

    /**
     * @internal
     */
    public const CONFIG_SEGMENTS = '__configSegments';

    /**
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensureMap(mixed $settings): array
    {
        if (!is_array($settings)) {
            throw new UnexpectedValueException('Setting map is invalid.');
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException
     *
     * @internal
     */
    public static function ensure(mixed $setting): array
    {
        if (!is_array($setting)) {
            throw new UnexpectedValueException('Setting is missing or invalid.');
        }

        return $setting;
    }

    /**
     * @param array<string, mixed> $setting
     *
     * @internal
     */
    public static function getType(array $setting): object
    {
        $settingType = $setting[self::TYPE];
        if ($settingType === self::unsupportedTypeToken()) {
            return $settingType;
        }

        return SettingType::from($settingType);
    }

    /**
     * @return array<string, mixed>
     *
     * @internal
     */
    public static function fromValue(mixed $value): array
    {
        if (is_bool($value)) {
            $settingType = SettingType::BOOLEAN->value;
            $value = [SettingValue::BOOLEAN => $value];
        } elseif (is_string($value)) {
            $settingType = SettingType::STRING->value;
            $value = [SettingValue::STRING => $value];
        } elseif (is_int($value)) {
            $settingType = SettingType::INT->value;
            $value = [SettingValue::INT => $value];
        } elseif (is_double($value)) {
            $settingType = SettingType::DOUBLE->value;
            $value = [SettingValue::DOUBLE => $value];
        } else {
            $settingType = self::unsupportedTypeToken();
        }

        return [
            self::TYPE => $settingType,
            self::VALUE => $value,
        ];
    }

    /**
     * Returns a token object for indicating an unsupported value coming from flag overrides.
     */
    private static function unsupportedTypeToken(): object
    {
        static $unsupportedTypeToken = null;

        return $unsupportedTypeToken ??= new stdClass();
    }
}
