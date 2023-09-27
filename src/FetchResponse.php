<?php

declare(strict_types=1);

namespace ConfigCat;

use ConfigCat\Cache\ConfigEntry;

/**
 * Represents a fetch response, including its state and body.
 *
 * @internal
 */
final class FetchResponse
{
    /** @var int */
    public const FETCHED = 0;

    /** @var int */
    public const NOT_MODIFIED = 1;

    /** @var int */
    public const FAILED = 3;

    private function __construct(
        private readonly int $status,
        private readonly ConfigEntry $cacheEntry,
        private readonly ?string $error = null
    ) {}

    /**
     * Creates a new response with FAILED status.
     *
     * @param string $error the reason of the failure
     *
     * @return FetchResponse the response
     */
    public static function failure(string $error): FetchResponse
    {
        return new FetchResponse(self::FAILED, ConfigEntry::empty(), $error);
    }

    /**
     * Creates a new response with NOT_MODIFIED status.
     *
     * @return FetchResponse the response
     */
    public static function notModified(): FetchResponse
    {
        return new FetchResponse(self::NOT_MODIFIED, ConfigEntry::empty(), null);
    }

    /**
     * Creates a new response with FETCHED status.
     *
     * @param ConfigEntry $entry the produced config entry
     *
     * @return FetchResponse the response
     */
    public static function success(ConfigEntry $entry): FetchResponse
    {
        return new FetchResponse(self::FETCHED, $entry, null);
    }

    /**
     * Returns true when the response is in fetched state.
     *
     * @return bool true if the fetch succeeded, otherwise false
     */
    public function isFetched(): bool
    {
        return self::FETCHED === $this->status;
    }

    /**
     * Returns true when the response is in not modified state.
     *
     * @return bool true if the fetched configurations was not modified, otherwise false
     */
    public function isNotModified(): bool
    {
        return self::NOT_MODIFIED === $this->status;
    }

    /**
     * Returns true when the response is in failed state.
     *
     * @return bool true if the fetch failed, otherwise false
     */
    public function isFailed(): bool
    {
        return self::FAILED === $this->status;
    }

    /**
     * Returns the produced config entry.
     *
     * @return ConfigEntry the produced config entry
     */
    public function getConfigEntry(): ConfigEntry
    {
        return $this->cacheEntry;
    }

    /**
     * Returns the error if the fetch failed.
     *
     * @return ?string the error
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
