<?php

namespace ConfigCat;

use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigFetcher This class is used to fetch the latest configuration.
 * @package ConfigCat
 */
final class ConfigFetcher
{
    const ETAG_HEADER = "ETag";
    const BASE_URL = "https://cdn.configcat.com";
    const URL_FORMAT = "/configuration-files/%s/config_v2.json";

    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $requestOptions;
    /** @var string */
    private $url;
    /** @var float */
    private $connectTimeout = 10;
    /** @var float */
    private $requestTimeout = 30;

    /**
     * ConfigFetcher constructor.
     *
     * @param string $apiKey The api key used to communicate with the ConfigCat services.
     * @param LoggerInterface $logger The logger instance.
     * @param array $options The http related configuration options:
     *     - timeout: sets the http request timeout of the underlying http requests.
     *     - connect-timeout: sets the http connect timeout.
     *     - custom-handler: a custom callable Guzzle http handler.
     *
     * @throws InvalidArgumentException
     *   When the $apiKey, the $logger or the $cache is not legal.
     */
    public function __construct($apiKey, LoggerInterface $logger, array $options = [])
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException("apiKey cannot be empty.");
        }

        if (isset($options['connect-timeout']) && is_numeric($options['connect-timeout'])) {
            $this->connectTimeout = $options['connect-timeout'];
        }

        if (isset($options['timeout']) && is_numeric($options['timeout'])) {
            $this->requestTimeout = $options['timeout'];
        }

        $this->logger = $logger;
        $this->url = sprintf(self::URL_FORMAT, $apiKey);
        $this->requestOptions = [
            'headers' => [
                'X-ConfigCat-UserAgent' => "ConfigCat-PHP/" . ConfigCatClient::SDK_VERSION
            ],
            'timeout' => $this->requestTimeout,
            'connect_timeout' => $this->connectTimeout
        ];

        if (isset($options['custom-handler']) && is_callable($options['custom-handler'])) {
            $this->client = new Client([
                'base_uri' => self::BASE_URL,
                'handler' => HandlerStack::create($options['custom-handler'])
            ]);
        } else {
            $this->client = new Client([
                'base_uri' => self::BASE_URL
            ]);
        }
    }

    /**
     * Gets the latest configuration from the network.
     *
     * @param string $etag The ETag.
     *
     * @return FetchResponse An object describing the result of the fetch.
     */
    public function fetch($etag)
    {
        if (!empty($etag)) {
            $this->requestOptions['headers']['If-None-Match'] = $etag;
        }

        try {
            $response = $this->client->get($this->url, $this->requestOptions);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->info("Fetch was successful: new config fetched");

                $body = json_decode($response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->logger->error(json_last_error_msg());
                    return new FetchResponse(FetchResponse::FAILED);
                }

                if ($response->hasHeader(self::ETAG_HEADER)) {
                    $etag = $response->getHeader(self::ETAG_HEADER)[0];
                }

                return new FetchResponse(FetchResponse::FETCHED, $etag, $body);
            } elseif ($statusCode === 304) {
                $this->logger->info("Fetch was successful: config not modified");
                return new FetchResponse(FetchResponse::NOT_MODIFIED);
            }

            $this->logger->warning("Non success status code: " . $statusCode);
            return new FetchResponse(FetchResponse::FAILED);
        } catch (Exception $exception) {
            $this->logger->error($exception->getMessage(), ['exception' => $exception]);
            return new FetchResponse(FetchResponse::FAILED);
        }
    }
}
