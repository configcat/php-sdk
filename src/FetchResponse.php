<?php

namespace ConfigCat;

/**
 * Represents a fetch response, including its state and body.
 * @package ConfigCat
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
        private readonly ?string $etag = null,
        private readonly ?array $body = null,
        private readonly ?string $url = null,
        private readonly ?string $error = null
    ) {
    }

    /**
     * Creates a new response with FAILED status.
     *
     * @param string $error the reason of the failure.
     * @return FetchResponse the response.
     */
    public static function failure(string $error): FetchResponse
    {
        return new FetchResponse(self::FAILED, null, null, null, $error);
    }

    /**
     * Creates a new response with NOT_MODIFIED status.
     *
     * @return FetchResponse the response.
     */
    public static function notModified(): FetchResponse
    {
        return new FetchResponse(self::NOT_MODIFIED, null, null, null, null);
    }

    /**
     * Creates a new response with FETCHED status.
     *
     * @param string|null $etag the ETag.
     * @param array $body the response body.
     * @param string $url the fetched url.
     * @return FetchResponse the response.
     */
    public static function success(?string $etag, array $body, string $url): FetchResponse
    {
        return new FetchResponse(self::FETCHED, $etag, $body, $url, null);
    }

    /**
     * Returns true when the response is in fetched state.
     *
     * @return bool True if the fetch succeeded, otherwise false.
     */
    public function isFetched(): bool
    {
        return $this->status === self::FETCHED;
    }

    /**
     * Returns true when the response is in not modified state.
     *
     * @return bool True if the fetched configurations was not modified, otherwise false.
     */
    public function isNotModified(): bool
    {
        return $this->status === self::NOT_MODIFIED;
    }

    /**
     * Returns true when the response is in failed state.
     *
     * @return bool True if the fetch failed, otherwise false.
     */
    public function isFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * Returns the response body.
     *
     * @return ?array The fetched response body.
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Returns the ETag.
     *
     * @return ?string The received ETag.
     */
    public function getETag(): ?string
    {
        return $this->etag;
    }

    /**
     * Returns the proper cdn url.
     *
     * @return ?string The cdn url.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Returns the error if the fetch failed.
     *
     * @return ?string The error.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
