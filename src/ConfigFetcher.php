<?php

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\Preferences;
use Exception;
use GuzzleHttp\Client;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigFetcher This class is used to fetch the latest configuration.
 * @package ConfigCat
 */
final class ConfigFetcher
{
    const ETAG_HEADER = "ETag";
    const URL_FORMAT = "configuration-files/%s/config_v5.json";

    const GLOBAL_URL = "https://cdn-global.configcat.com";
    const EU_ONLY_URL = "https://cdn-eu.configcat.com";

    /** @var LoggerInterface */
    private $logger;
    /** @var array */
    private $requestOptions;
    /** @var array */
    private $clientOptions;
    /** @var string */
    private $urlPath;
    /** @var string */
    private $baseUrl;
    /** @var bool */
    private $urlIsCustom = false;

    /**
     * ConfigFetcher constructor.
     *
     * @param string $sdkKey The SDK Key used to communicate with the ConfigCat services.
     * @param LoggerInterface $logger The logger instance.
     * @param array $options The http related configuration options:
     *     - timeout: sets the http request timeout of the underlying http requests.
     *     - connect-timeout: sets the http connect timeout.
     *     - custom-handler: a custom callable Guzzle http handler.
     *     - base-url: the base ConfigCat CDN url.
     *
     * @throws InvalidArgumentException
     *   When the $sdkKey, the $logger or the $cache is not legal.
     */
    public function __construct($sdkKey, LoggerInterface $logger, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("sdkKey cannot be empty.");
        }

        $this->urlPath = sprintf(self::URL_FORMAT, $sdkKey);

        if (isset($options['base-url']) && !empty($options['base-url'])) {
            $this->baseUrl = $options['base-url'];
            $this->urlIsCustom = true;
        } elseif (isset($options['data-governance']) && DataGovernance::isValid($options['data-governance'])) {
            $this->baseUrl = DataGovernance::isEuOnly($options['data-governance'])
                ? self::EU_ONLY_URL
                : self::GLOBAL_URL;
        } else {
            $this->baseUrl = self::GLOBAL_URL;
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
        $this->requestOptions = array_merge([
            'headers' => [
                'X-ConfigCat-UserAgent' => "ConfigCat-PHP/" . ConfigCatClient::SDK_VERSION
            ],
        ], $additionalOptions);

        $this->clientOptions = isset($options['custom-handler'])
            ? ['handler' => $options['custom-handler']]
            : [];
    }

    /**
     * Gets the latest configuration from the network.
     *
     * @param string $etag The ETag.
     * @param string $cachedUrl The cached cdn url.
     *
     * @return FetchResponse An object describing the result of the fetch.
     */
    public function fetch($etag, $cachedUrl)
    {
        return $this->executeFetch($etag, !empty($cachedUrl) ? $cachedUrl : $this->baseUrl, 2);
    }

    public function getRequestOptions()
    {
        return $this->requestOptions;
    }

    private function executeFetch($etag, $url, $executionCount)
    {
        $response = $this->sendConfigFetchRequest($etag, $url);

        if (!$response->isFetched() || !isset($response->getBody()[Config::PREFERENCES])) {
            return $response;
        }

        $preferences = $response->getBody()[Config::PREFERENCES];
        $newUrl = $preferences[Preferences::BASE_URL];
        if (empty($newUrl) || $newUrl == $url) {
            return $response;
        }

        $redirect = $preferences[Preferences::REDIRECT];
        if ($this->urlIsCustom && $redirect != 2) {
            return $response;
        }

        if ($redirect == 0) {
            return $response;
        } else {
            if ($redirect == 1) {
                $this->logger->warning("Your config.DataGovernance parameter at ConfigCatClient ".
                        "initialization is not in sync with your preferences on the ConfigCat " .
                        "Dashboard: https://app.configcat.com/organization/data-governance. " .
                        "Only Organization Admins can set this preference.");
            }

            if ($executionCount > 0) {
                return $this->executeFetch($etag, $newUrl, $executionCount - 1);
            }
        }

        return $response;
    }

    private function sendConfigFetchRequest($etag, $url)
    {
        if (!empty($etag)) {
            $this->requestOptions['headers']['If-None-Match'] = $etag;
        }

        try {
            $client = $this->createClient($url);
            $response = $client->get($this->urlPath, $this->requestOptions);
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

                $url = "";
                if (isset($body[Config::PREFERENCES]) && isset($body[Config::PREFERENCES][Preferences::BASE_URL])) {
                    $url = $body[Config::PREFERENCES][Preferences::BASE_URL];
                }

                return new FetchResponse(FetchResponse::FETCHED, $etag, $body, $url);
            } elseif ($statusCode === 304) {
                $this->logger->debug("Fetch was successful: config not modified.");
                return new FetchResponse(FetchResponse::NOT_MODIFIED);
            }

            $this->logger->error("Double-check your SDK Key at https://app.configcat.com/sdkkey. " .
                "Received unexpected response: " . $statusCode);
            return new FetchResponse(FetchResponse::FAILED);
        } catch (Exception $exception) {
            $this->logger->error("Exception in ConfigFetcher.getConfigurationJsonStringAsync: "
                . $exception->getMessage(), ['exception' => $exception]);
            return new FetchResponse(FetchResponse::FAILED);
        }
    }

    private function createClient($baseUrl)
    {
        return new Client(array_merge([
            'base_uri' => $baseUrl
        ], $this->clientOptions));
    }
}
