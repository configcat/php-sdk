<?php

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\Preferences;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigFetcher This class is used to fetch the latest configuration.
 * @package ConfigCat
 */
final class ConfigFetcher
{
    const ETAG_HEADER = "ETag";
    const CONFIG_JSON_NAME = "config_v5";

    const GLOBAL_URL = "https://cdn-global.configcat.com";
    const EU_ONLY_URL = "https://cdn-eu.configcat.com";

    const NO_REDIRECT = 0;
    const SHOULD_REDIRECT = 1;
    const FORCE_REDIRECT = 2;

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
     * @param array $options The additional configuration options:
     *     - base-url: The base ConfigCat CDN url.
     *     - data-governance: Default: Global. Set this parameter to be in sync with the Data Governance.
     *     - custom-handler: A custom callable Guzzle http handler.
     *     - request-options: Additional options for Guzzle http requests.
     *                        https://docs.guzzlephp.org/en/stable/request-options.html
     *
     * @throws InvalidArgumentException
     *   When the $sdkKey, the $logger or the $cache is not legal.
     */
    public function __construct($sdkKey, LoggerInterface $logger, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException("sdkKey cannot be empty.");
        }

        $this->urlPath = sprintf("configuration-files/%s/" . self::CONFIG_JSON_NAME . ".json", $sdkKey);

        if (isset($options[ClientOptions::BASE_URL]) && !empty($options[ClientOptions::BASE_URL])) {
            $this->baseUrl = $options[ClientOptions::BASE_URL];
            $this->urlIsCustom = true;
        } elseif (isset($options[ClientOptions::DATA_GOVERNANCE]) &&
            DataGovernance::isValid($options[ClientOptions::DATA_GOVERNANCE])) {
            $this->baseUrl = DataGovernance::isEuOnly($options[ClientOptions::DATA_GOVERNANCE])
                ? self::EU_ONLY_URL
                : self::GLOBAL_URL;
        } else {
            $this->baseUrl = self::GLOBAL_URL;
        }

        $additionalOptions = isset($options[ClientOptions::REQUEST_OPTIONS])
        && is_array($options[ClientOptions::REQUEST_OPTIONS])
        && !empty($options[ClientOptions::REQUEST_OPTIONS])
            ? $options[ClientOptions::REQUEST_OPTIONS]
            : [];

        if (!isset($additionalOptions[RequestOptions::CONNECT_TIMEOUT])) {
            $additionalOptions[RequestOptions::CONNECT_TIMEOUT] = 10;
        }

        if (!isset($additionalOptions[RequestOptions::TIMEOUT])) {
            $additionalOptions[RequestOptions::TIMEOUT] = 30;
        }

        $this->logger = $logger;
        $this->requestOptions = array_merge([
            'headers' => [
                'X-ConfigCat-UserAgent' => "ConfigCat-PHP/" . ConfigCatClient::SDK_VERSION
            ],
        ], $additionalOptions);

        $this->clientOptions = isset($options[ClientOptions::CUSTOM_HANDLER])
            ? ['handler' => $options[ClientOptions::CUSTOM_HANDLER]]
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

        $newUrl = $response->getUrl();
        if (empty($newUrl) || $newUrl == $url) {
            return $response;
        }

        $preferences = $response->getBody()[Config::PREFERENCES];
        $redirect = $preferences[Preferences::REDIRECT];
        if ($this->urlIsCustom && $redirect != self::FORCE_REDIRECT) {
            return $response;
        }

        if ($redirect == self::NO_REDIRECT) {
            return $response;
        }

        if ($redirect == self::SHOULD_REDIRECT) {
            $this->logger->warning("Your config.DataGovernance parameter at ConfigCatClient " .
                "initialization is not in sync with your preferences on the ConfigCat " .
                "Dashboard: https://app.configcat.com/organization/data-governance. " .
                "Only Organization Admins can access this preference.");
        }

        if ($executionCount > 0) {
            return $this->executeFetch($etag, $newUrl, $executionCount - 1);
        }

        $this->logger->error("Redirect loop during config.json fetch. Please contact support@configcat.com.");
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

                $newUrl = "";
                if (isset($body[Config::PREFERENCES]) && isset($body[Config::PREFERENCES][Preferences::BASE_URL])) {
                    $newUrl = $body[Config::PREFERENCES][Preferences::BASE_URL];
                }

                return new FetchResponse(FetchResponse::FETCHED, $etag, $body, $newUrl);
            } elseif ($statusCode === 304) {
                $this->logger->debug("Fetch was successful: config not modified.");
                return new FetchResponse(FetchResponse::NOT_MODIFIED);
            }

            $this->logger->error("Double-check your SDK Key at https://app.configcat.com/sdkkey. " .
                "Received unexpected response: " . $statusCode);
            return new FetchResponse(FetchResponse::FAILED);
        } catch (GuzzleException $exception) {
            $this->logger->error("HTTP exception: "
                . $exception->getMessage(), ['exception' => $exception]);
            return new FetchResponse(FetchResponse::FAILED);
        } catch (Exception $exception) {
            $this->logger->error("Exception in ConfigFetcher.sendConfigFetchRequest: "
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
