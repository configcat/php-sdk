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
    const FETCHED = 0;
    /** @var int */
    const NOT_MODIFIED = 1;
    /** @var int */
    const FAILED = 3;

    /** @var array|null */
    private $body;
    /** @var int */
    private $status;
    /** @var string|null */
    private $etag;
    /** @var string */
    private $url;
    /** @var string|null */
    private $error;

    private function __construct(int $status, ?string $etag = null, ?array $body = null, ?string $url = null, ?string $error = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->etag = $etag;
        $this->url = $url;
        $this->error = $error;
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
     * @return array|null The fetched response body.
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Returns the ETag.
     *
     * @return string|null The received ETag.
     */
    public function getETag(): ?string
    {
        return $this->etag;
    }

    /**
     * Returns the proper cdn url.
     *
     * @return string|null The cdn url.
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Returns the error if the fetch failed.
     *
     * @return string|null The error.
     */
    public function getError(): ?string
    {
        return $this->error;
    }
}
