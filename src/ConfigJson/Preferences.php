<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * Represents the JSON keys of the Preferences section in the ConfigCat config_v6.json file.
 */
abstract class Preferences
{
    /**
     * The base url from where the config.json is intended to be downloaded.
     */
    public const BASE_URL = 'u';

    /**
     * The redirect mode that should be used in case the data governance mode is wrongly configured.
     */
    public const REDIRECT = 'r';

    /**
     * The salt that, combined with the feature flag key or segment name, is used to hash values for sensitive text comparisons.
     */
    public const SALT = 's';
}
