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
    /** @var string */
    private $filePath;

    /**
     * Constructs a local file data source.
     * @param $filePath string The path to the file.
     * @param $behaviour int The override behaviour.
     */
    public function __construct($filePath, $behaviour)
    {
        if (!file_exists($filePath)) {
            throw new InvalidArgumentException("The file '" . $filePath . "' doesn't exist.");
        }

        parent::__construct($behaviour);
        $this->filePath = $filePath;
    }

    /**
     * Gets the overrides.
     * @return array The overrides.
     */
    public function getOverrides()
    {
        $content = file_get_contents($this->filePath);
        if ($content === false) {
            $this->logger->error("Could not read the contents of the file " . $this->filePath . ".");
            return null;
        }

        $json = json_decode($content, true);

        if ($json == null) {
            $this->logger->error("Could not decode json from file " . $this->filePath . ". JSON error: 
                " . json_last_error_msg() . "");
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
