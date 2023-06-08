<?php

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\Preferences;
use ConfigCat\Log\InternalLogger;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;

/**
 * Class ConfigFetcher This class is used to fetch the latest configuration.
 * @package ConfigCat
 * @internal
 */
final class ConfigFetcher
{
    public const ETAG_HEADER = "ETag";
    public const CONFIG_JSON_NAME = "config_v5";

    public const GLOBAL_URL = "https://cdn-global.configcat.com";
    public const EU_ONLY_URL = "https://cdn-eu.configcat.com";

    public const NO_REDIRECT = 0;
    public const SHOULD_REDIRECT = 1;
    public const FORCE_REDIRECT = 2;

    private InternalLogger $logger;
    private array $requestOptions;
    private array $clientOptions;
    private string $urlPath;
    private string $baseUrl;
    private bool $urlIsCustom = false;
    private Client $client;

    /**
     * ConfigFetcher constructor.
     *
     * @param string $sdkKey The SDK Key used to communicate with the ConfigCat services.
     * @param InternalLogger $logger The logger instance.
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
    public function __construct(string $sdkKey, InternalLogger $logger, array $options = [])
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

        $this->client = new Client($this->clientOptions);
    }

    /**
     * Gets the latest configuration from the network.
     *
     * @param ?string $etag The ETag.
     *
     * @return FetchResponse An object describing the result of the fetch.
     */
    public function fetch(?string $etag): FetchResponse
    {
        return $this->executeFetch($etag, $this->baseUrl, 2);
    }

    public function getRequestOptions(): array
    {
        return $this->requestOptions;
    }

    private function executeFetch(?string $etag, string $url, int $executionCount): FetchResponse
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
            $this->logger->warning(
                "The `dataGovernance` parameter specified at the client initialization is ".
                "not in sync with the preferences on the ConfigCat Dashboard. " .
                "Read more: https://configcat.com/docs/advanced/data-governance/",
                [
                    'event_id' => 3002
                ]
            );
        }

        if ($executionCount > 0) {
            return $this->executeFetch($etag, $newUrl, $executionCount - 1);
        }

        $this->logger->error("Redirection loop encountered while trying to fetch config JSON. Please contact us at https://configcat.com/support/", [
            'event_id' => 1104
        ]);
        return $response;
    }

    private function sendConfigFetchRequest(?string $etag, string $url): FetchResponse
    {
        if (!empty($etag)) {
            $this->requestOptions['headers']['If-None-Match'] = $etag;
        }

        try {
            $configJsonUrl = sprintf("%s/%s", $url, $this->urlPath);
            $response = $this->client->get($configJsonUrl, $this->requestOptions);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->debug("Fetch was successful: new config fetched.");

                $body = json_decode($response->getBody(), true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $message = "Fetching config JSON was successful but the HTTP response content was invalid. JSON error: {JSON_ERROR}";
                    $messageCtx = [
                        'event_id' => 1105,
                        'JSON_ERROR' => json_last_error_msg()
                    ];
                    $this->logger->error($message, $messageCtx);
                    return FetchResponse::failure(InternalLogger::format($message, $messageCtx));
                }

                if ($response->hasHeader(self::ETAG_HEADER)) {
                    $etag = $response->getHeader(self::ETAG_HEADER)[0];
                }

                $newUrl = "";
                if (isset($body[Config::PREFERENCES]) && isset($body[Config::PREFERENCES][Preferences::BASE_URL])) {
                    $newUrl = $body[Config::PREFERENCES][Preferences::BASE_URL];
                }

                return FetchResponse::success($etag, $body, $newUrl);
            } elseif ($statusCode === 304) {
                $this->logger->debug("Fetch was successful: config not modified.");
                return FetchResponse::notModified();
            }

            $message = "Your SDK Key seems to be wrong. You can find the valid SDK Key at https://app.configcat.com/sdkkey. " .
                "Received unexpected response: {STATUS_CODE}";
            $messageCtx = [
                'event_id' => 1100,
                'STATUS_CODE' => $statusCode
            ];
            $this->logger->error($message, $messageCtx);
            return FetchResponse::failure(InternalLogger::format($message, $messageCtx));
        } catch (ConnectException $exception) {
            $connTimeout = $this->requestOptions[RequestOptions::CONNECT_TIMEOUT];
            $timeout = $this->requestOptions[RequestOptions::TIMEOUT];
            $message = "Request timed out while trying to fetch config JSON. Timeout values: [connect: {CONN_TIMEOUT}s, timeout: {TIMEOUT}s]";
            $messageCtx = [
                'event_id' => 1102, 'exception' => $exception,
                'CONN_TIMEOUT' => $connTimeout, 'TIMEOUT' => $timeout,
            ];
            $this->logger->error($message, $messageCtx);
            return FetchResponse::failure(InternalLogger::format($message, $messageCtx));
        } catch (GuzzleException $exception) {
            $message = "Unexpected HTTP response was received while trying to fetch config JSON.";
            $messageCtx = ['event_id' => 1101, 'exception' => $exception];
            $this->logger->error($message, $messageCtx);
            return FetchResponse::failure($message);
        } catch (Exception $exception) {
            $message = "Unexpected error occurred while trying to fetch config JSON.";
            $messageCtx = ['event_id' => 1103, 'exception' => $exception];
            $this->logger->error($message, $messageCtx);
            return FetchResponse::failure($message);
        }
    }
}
