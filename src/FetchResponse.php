<?php

namespace ConfigCat;

/**
 * Class FetchResponse Represents a fetch response, including its state and body.
 * @package ConfigCat
 */
final class FetchResponse
{
    /** @var int */
    const FETCHED = 0;
    /** @var int */
    const NOT_MODIFIED = 1;
    /** @var int */
    const FAILED = 3;

    /** @var array|null  */
    private $body;
    /** @var int */
    private $status;
    /** @var string */
    private $etag;

    /**
     * FetchResponse constructor.
     *
     * @param int $status The fetch status code.
     * @param string|null The received ETag.
     * @param array|null $body The decoded JSON configuration.
     */
    public function __construct($status, $etag = null, $body = null)
    {
        $this->status = $status;
        $this->body = $body;
        $this->etag = $etag;
    }

    /**
     * Returns true when the response is in fetched state.
     *
     * @return bool True if the fetch succeeded, otherwise false.
     */
    public function isFetched()
    {
        return $this->status === self::FETCHED;
    }

    /**
     * Returns true when the response is in not modified state.
     *
     * @return bool True if the fetched configurations was not modified, otherwise false.
     */
    public function isNotModified()
    {
        return $this->status === self::NOT_MODIFIED;
    }

    /**
     * Returns true when the response is in failed state.
     *
     * @return bool True if the fetch failed, otherwise false.
     */
    public function isFailed()
    {
        return $this->status === self::FAILED;
    }

    /**
     * Returns the response body.
     *
     * @return array|null The fetched response body.
     */
    public function getBody()
    {
        return $this->body;
    }

    /**
     * Returns the ETag.
     *
     * @return string|null The received ETag.
     */
    public function getETag()
    {
        return $this->etag;
    }
}