<?php

namespace ConfigCat\Override;

use ConfigCat\Attributes\Config;
use ConfigCat\Attributes\SettingAttributes;
use InvalidArgumentException;

/**
 * Describes a local file override data source.
 * @package ConfigCat
 */
class LocalFileDataSource extends OverrideDataSource
{
    /**
     * Constructs a local file data source.
     * @param $filePath string The path to the file.
     */
    public function __construct(private readonly string $filePath)
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("The file '" . $filePath . "' doesn't exist.");
        }
    }

    /**
     * Gets the overrides.
     * @return ?array The overrides.
     */
    public function getOverrides(): ?array
    {
        $content = file_get_contents($this->filePath);
        if ($content === false) {
            $this->logger->error("Could not read the contents of the file " . $this->filePath . ".");
            return null;
        }

        $json = json_decode($content, true);

        if ($json == null) {
            $this->logger->error("Could not decode json from file " . $this->filePath . ". JSON error: 
                " . json_last_error_msg());
            return null;
        }

        if (isset($json['flags'])) {
            $result = [];
            foreach ($json['flags'] as $key => $value) {
                $result[$key] = [
                    SettingAttributes::VALUE => $value
                ];
            }
            return $result;
        }
        return $json[Config::ENTRIES];
    }
}
