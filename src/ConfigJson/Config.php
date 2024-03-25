<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

use UnexpectedValueException;

/**
 * Represents the root JSON keys of a ConfigCat config_v6.json file.
 */
final class Config
{
    /**
     * Preferences of the config.json, mostly for controlling the redirection behaviour of the SDK.
     */
    public const PREFERENCES = 'p';

    /**
     * Segment definitions for re-using segment rules in targeting rules.
     */
    public const SEGMENTS = 's';

    /**
     * Setting definitions.
     */
    public const SETTINGS = 'f';

    private function __construct() {}

    /**
     * @param array<string, mixed> $config
     *
     * @internal
     */
    public static function fixupSaltAndSegments(array &$config): void
    {
        $settings = &$config[self::SETTINGS] ?? [];
        if (is_array($settings) && !empty($settings)) {
            $salt = $config[self::PREFERENCES][Preferences::SALT] ?? null;
            $segments = $config[self::SEGMENTS] ?? [];

            foreach ($settings as &$setting) {
                $setting[Setting::CONFIG_JSON_SALT] = $salt;
                $setting[Setting::CONFIG_SEGMENTS] = $segments;
            }
        }
    }

    /**
     * @return array<string, mixed>
     *
     * @throws UnexpectedValueException
     */
    public static function deserialize(string $json): array
    {
        $config = json_decode($json, true);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new UnexpectedValueException('JSON error: '.json_last_error_msg());
        }

        if (!is_array($config)) {
            throw new UnexpectedValueException('Invalid config JSON content: '.$json);
        }

        self::fixupSaltAndSegments($config);

        return $config;
    }
}
