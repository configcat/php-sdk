<?php

namespace ConfigCat;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\Preferences;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * Class ConfigFetcher This class is used to fetch the latest configuration.
 *
 * @internal
 */
final class ConfigFetcher
{
    public const ETAG_HEADER = 'ETag';
    public const CONFIG_JSON_NAME = 'config_v5';

    public const GLOBAL_URL = 'https://cdn-global.configcat.com';
    public const EU_ONLY_URL = 'https://cdn-eu.configcat.com';

    public const NO_REDIRECT = 0;
    public const SHOULD_REDIRECT = 1;
    public const FORCE_REDIRECT = 2;
    private array $requestOptions;
    private readonly array $clientOptions;
    private readonly string $urlPath;
    private string $baseUrl;
    private bool $urlIsCustom = false;
    private readonly Client $client;

    /**
     * ConfigFetcher constructor.
     *
     * @param string          $sdkKey  the SDK Key used to communicate with the ConfigCat services
     * @param LoggerInterface $logger  the logger instance
     * @param array           $options The additional configuration options:
     *                                 - base-url: The base ConfigCat CDN url.
     *                                 - data-governance: Default: Global. Set this parameter to be in sync with the Data Governance.
     *                                 - custom-handler: A custom callable Guzzle http handler.
     *                                 - request-options: Additional options for Guzzle http requests.
     *                                 https://docs.guzzlephp.org/en/stable/request-options.html
     *
     * @throws invalidArgumentException
     *                                  When the $sdkKey, the $logger or the $cache is not legal
     */
    public function __construct(string $sdkKey, private readonly LoggerInterface $logger, array $options = [])
    {
        if (empty($sdkKey)) {
            throw new InvalidArgumentException('sdkKey cannot be empty.');
        }

        $this->urlPath = sprintf('configuration-files/%s/'.self::CONFIG_JSON_NAME.'.json', $sdkKey);

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
        && \is_array($options[ClientOptions::REQUEST_OPTIONS])
        && !empty($options[ClientOptions::REQUEST_OPTIONS])
            ? $options[ClientOptions::REQUEST_OPTIONS]
            : [];

        if (!isset($additionalOptions[RequestOptions::CONNECT_TIMEOUT])) {
            $additionalOptions[RequestOptions::CONNECT_TIMEOUT] = 10;
        }

        if (!isset($additionalOptions[RequestOptions::TIMEOUT])) {
            $additionalOptions[RequestOptions::TIMEOUT] = 30;
        }
        $this->requestOptions = array_merge([
            'headers' => [
                'X-ConfigCat-UserAgent' => 'ConfigCat-PHP/'.ConfigCatClient::SDK_VERSION,
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
     * @param string|null $etag      the ETag
     * @param string|null $cachedUrl the cached cdn url
     *
     * @return FetchResponse an object describing the result of the fetch
     */
    public function fetch(?string $etag, ?string $cachedUrl): FetchResponse
    {
        return $this->executeFetch($etag, empty($cachedUrl) ? $this->baseUrl : $cachedUrl, 2);
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
        if (empty($newUrl) || $newUrl === $url) {
            return $response;
        }

        $preferences = $response->getBody()[Config::PREFERENCES];
        $redirect = $preferences[Preferences::REDIRECT];
        if ($this->urlIsCustom && self::FORCE_REDIRECT != $redirect) {
            return $response;
        }

        if (self::NO_REDIRECT == $redirect) {
            return $response;
        }

        if (self::SHOULD_REDIRECT == $redirect) {
            $this->logger->warning('Your data-governance parameter at ConfigCatClient '.
                'initialization is not in sync with your preferences on the ConfigCat '.
                'Dashboard: https://app.configcat.com/organization/data-governance. '.
                'Only Organization Admins can access this preference.');
        }

        if ($executionCount > 0) {
            return $this->executeFetch($etag, $newUrl, $executionCount - 1);
        }

        $this->logger->error('Redirect loop during config.json fetch. Please contact support@configcat.com.');

        return $response;
    }

    private function sendConfigFetchRequest(?string $etag, string $url): FetchResponse
    {
        if (!empty($etag)) {
            $this->requestOptions['headers']['If-None-Match'] = $etag;
        }

        try {
            $configJsonUrl = sprintf('%s/%s', $url, $this->urlPath);
            $response = $this->client->get($configJsonUrl, $this->requestOptions);
            $statusCode = $response->getStatusCode();

            if ($statusCode >= 200 && $statusCode < 300) {
                $this->logger->debug('Fetch was successful: new config fetched.');

                $body = json_decode($response->getBody(), true);
                if (\JSON_ERROR_NONE !== json_last_error()) {
                    $message = json_last_error_msg();
                    $this->logger->error($message);

                    return FetchResponse::failure($message);
                }

                if ($response->hasHeader(self::ETAG_HEADER)) {
                    $etag = $response->getHeader(self::ETAG_HEADER)[0];
                }

                $newUrl = '';
                if (isset($body[Config::PREFERENCES]) && isset($body[Config::PREFERENCES][Preferences::BASE_URL])) {
                    $newUrl = $body[Config::PREFERENCES][Preferences::BASE_URL];
                }

                return FetchResponse::success($etag, $body, $newUrl);
            } elseif (304 === $statusCode) {
                $this->logger->debug('Fetch was successful: config not modified.');

                return FetchResponse::notModified();
            }

            $message = 'Double-check your SDK Key at https://app.configcat.com/sdkkey. '.
                'Received unexpected response: '.$statusCode;
            $this->logger->error($message);

            return FetchResponse::failure($message);
        } catch (ConnectException $exception) {
            $connTimeout = $this->requestOptions[RequestOptions::CONNECT_TIMEOUT];
            $timeout = $this->requestOptions[RequestOptions::TIMEOUT];
            $message = 'Request timed out. Timeout values: [connect: '.$connTimeout.'s, timeout:'.$timeout.'s]';
            $this->logger->error($message, ['exception' => $exception]);

            return FetchResponse::failure($message);
        } catch (GuzzleException $exception) {
            $message = 'HTTP exception: '.$exception->getMessage();
            $this->logger->error($message, ['exception' => $exception]);

            return FetchResponse::failure($message);
        } catch (Exception $exception) {
            $message = 'Exception during fetch: '.$exception->getMessage();
            $this->logger->error($message, ['exception' => $exception]);

            return FetchResponse::failure($message);
        }
    }
}
