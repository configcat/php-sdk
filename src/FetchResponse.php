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
    /** @var string */
    private $etag;
    /** @var string */
    private $url;
    /** @var string|null */
    private $error;

    /**
     * FetchResponse constructor.
     *
     * @param int $status The fetch status code.
     * @param string|null $etag The received ETag.
     * @param array|null $body The decoded JSON configuration.
     * @param string|null $url The url pointing to the proper cdn server.
     */
    public function __construct(int $status, string $etag = null, ?array $body = null, ?string $url = null, ?string $error = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->etag = $etag;
        $this->url = $url;
        $this->error = $error;
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
