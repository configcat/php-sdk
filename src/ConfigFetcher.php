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
    const URL_FORMAT = "/configuration-files/%s/config_v4.json";

    /** @var Client */
    private $client;
    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $requestOptions;
    /** @var string */
    private $url;
    /** @var string */
    private $baseUrl = "https://cdn.configcat.com";

    /**
     * ConfigFetcher constructor.
     *
     * @param string $apiKey The api key used to communicate with the ConfigCat services.
     * @param LoggerInterface $logger The logger instance.
     * @param array $options The http related configuration options:
     *     - timeout: sets the http request timeout of the underlying http requests.
     *     - connect-timeout: sets the http connect timeout.
     *     - custom-handler: a custom callable Guzzle http handler.
     *     - base-url: the base ConfigCat CDN url.
     *
     * @throws InvalidArgumentException
     *   When the $apiKey, the $logger or the $cache is not legal.
     */
    public function __construct($apiKey, LoggerInterface $logger, array $options = [])
    {
        if (empty($apiKey)) {
            throw new InvalidArgumentException("apiKey cannot be empty.");
        }

        if (isset($options['base-url']) && !empty($options['base-url'])) {
            $this->baseUrl = $options['base-url'];
        }

        $additionalOptions = isset($options['request-options'])
            && is_array($options['request-options'])
            && !empty($options['request-options'])
            ? $options['request-options']
            : [];

        if (!isset($additionalOptions['connect-timeout'])) {
            $additionalOptions['connect-timeout'] = 10;
        }

        if (!isset($additionalOptions['timeout'])) {
            $additionalOptions['timeout'] = 30;
        }

        $this->logger = $logger;
        $this->url = sprintf(self::URL_FORMAT, $apiKey);
        $this->requestOptions = array_merge([
            'headers' => [
                'X-ConfigCat-UserAgent' => "ConfigCat-PHP/" . ConfigCatClient::SDK_VERSION
            ],
        ], $additionalOptions);

        if (isset($options['custom-handler']) && is_callable($options['custom-handler'])) {
            $this->client = new Client([
                'base_uri' => $this->baseUrl,
                'handler' => HandlerStack::create($options['custom-handler'])
            ]);
        } else {
            $this->client = new Client([
                'base_uri' => $this->baseUrl
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
                $this->logger->debug("Fetch was successful: new config fetched.");

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
                $this->logger->debug("Fetch was successful: config not modified.");
                return new FetchResponse(FetchResponse::NOT_MODIFIED);
            }

            $this->logger->error("Double-check your API KEY at https://app.configcat.com/apikey. " .
                "Received unexpected response: " . $statusCode);
            return new FetchResponse(FetchResponse::FAILED);
        } catch (Exception $exception) {
            $this->logger->error("Exception in ConfigFetcher.getConfigurationJsonStringAsync: "
                . $exception->getMessage(), ['exception' => $exception]);
            return new FetchResponse(FetchResponse::FAILED);
        }
    }

    public function getRequestOptions()
    {
        return $this->requestOptions;
    }
}
