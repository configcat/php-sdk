<?php

declare(strict_types=1);

namespace ConfigCat\Http;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;

interface FetchClientInterface
{
    /**
     * Returns with the underlying HTTP client.
     *
     * @return ClientInterface the actual HTTP client
     */
    public function getClient(): ClientInterface;

    /**
     * Creates an HTTP request with the given parameters.
     *
     * @param string $method the HTTP method
     * @param string $uri    the URI the request points to
     *
     * @return RequestInterface the actual HTTP request object
     */
    public function createRequest(string $method, string $uri): RequestInterface;
}
