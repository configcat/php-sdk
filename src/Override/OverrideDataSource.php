<?php

namespace ConfigCat\Override;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes an override data source.
 * @package ConfigCat
 */
abstract class OverrideDataSource implements LoggerAwareInterface
{
    /** @var LoggerInterface */
    protected $logger;

    /**
     * Gets the overrides.
     * @return array The overrides.
     */
    abstract public function getOverrides();

    /**
     * Sets the logger.
     * @param LoggerInterface $logger The logger.
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Creates an override data source that reads the overrides from a file.
     * @param $filePath string The path to the file.
     * @return OverrideDataSource The constructed data source.
     */
    public static function localFile($filePath)
    {
        return new LocalFileDataSource($filePath);
    }

    /**
     * Creates an override data source that reads the overrides from an array.
     * @param $overrides array The array that contains the overrides.
     * @return OverrideDataSource The constructed data source.
     */
    public static function localArray($overrides)
    {
        return new ArrayDataSource($overrides);
    }
}
