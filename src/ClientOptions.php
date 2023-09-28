<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * Contains the configuration options of the \ConfigCat\ConfigCatClient.
 */
final class ClientOptions
{
    /**
     * The base ConfigCat CDN url.
     */
    public const BASE_URL = 'base-url';

    /**
     * A \Psr\Log\LoggerInterface implementation used for logging.
     */
    public const LOGGER = 'logger';

    /**
     * Default: Warning. Sets the internal log level.
     */
    public const LOG_LEVEL = 'log-level';

    /**
     * A \ConfigCat\ConfigCache implementation used for caching.
     */
    public const CACHE = 'cache';

    /**
     * Array of exception classes that should be ignored from logs.
     */
    public const EXCEPTIONS_TO_IGNORE = 'exceptions-to-ignore';

    /**
     * Sets how frequent the cached configuration should be refreshed in seconds.
     */
    public const CACHE_REFRESH_INTERVAL = 'cache-refresh-interval';

    /**
     * Additional options for Guzzle http requests.
     * https://docs.guzzlephp.org/en/stable/request-options.html.
     *
     * @deprecated use \ConfigCat\ClientOptions::FETCH_CLIENT option instead with \ConfigCat\Http\GuzzleFetchClient::create()
     */
    public const REQUEST_OPTIONS = 'request-options';

    /**
     * A custom callable Guzzle http handler.
     *
     * @deprecated use \ConfigCat\ClientOptions::FETCH_CLIENT option instead with \ConfigCat\Http\GuzzleFetchClient::create()
     */
    public const CUSTOM_HANDLER = 'custom-handler';

    /**
     * A \ConfigCat\Http\FetchClientInterface implementation that wraps an actual HTTP client used
     * to make HTTP requests towards ConfigCat.
     * When it's not set, \ConfigCat\Http\GuzzleFetchClient is used by default.
     */
    public const FETCH_CLIENT = 'fetch-client';

    /**
     * Default: Global. Set this parameter to be in sync with the Data Governance
     * preference on the Dashboard: https://app.configcat.com/organization/data-governance
     * (Only Organization Admins can access).
     */
    public const DATA_GOVERNANCE = 'data-governance';

    /**
     * A \ConfigCat\Override\OverrideDataSource implementation used to override
     * feature flags & settings.
     */
    public const FLAG_OVERRIDES = 'flag-overrides';

    /**
     * A \ConfigCat\User as default user.
     */
    public const DEFAULT_USER = 'default-user';

    /**
     * Default: false. Indicates whether the SDK should be initialized in offline mode or not.
     */
    public const OFFLINE = 'offline';
}
