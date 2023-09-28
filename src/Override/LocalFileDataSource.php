<?php

declare(strict_types=1);

namespace ConfigCat\Override;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\SettingAttributes;
use InvalidArgumentException;

/**
 * Describes a local file override data source.
 */
class LocalFileDataSource extends OverrideDataSource
{
    /**
     * Constructs a local file data source.
     *
     * @param string $filePath the path to the file
     */
    public function __construct(private readonly string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("The file '".$filePath."' doesn't exist.");
        }
    }

    /**
     * Gets the overrides.
     *
     * @return mixed[] the overrides
     */
    public function getOverrides(): array
    {
        $content = file_get_contents($this->filePath);
        if (false === $content) {
            $this->logger->error("Cannot find the local config file '".$this->filePath."'. ' .
                'This is a path that your application provided to the ConfigCat SDK by passing it to the `FlagOverrides.LocalFile()` method. ' .
                'Read more: https://configcat.com/docs/sdk-reference/php/#json-file", [
                'event_id' => 1300,
            ]);

            return [];
        }

        $json = json_decode($content, true);

        if (null == $json) {
            $this->logger->error("Failed to decode JSON from the local config file '".$this->filePath."'. JSON error: ".json_last_error_msg(), [
                'event_id' => 2302,
            ]);

            return [];
        }

        if (isset($json['flags'])) {
            $result = [];
            foreach ($json['flags'] as $key => $value) {
                $result[$key] = [
                    SettingAttributes::VALUE => $value,
                ];
            }

            return $result;
        }

        return $json[Config::ENTRIES];
    }
}
