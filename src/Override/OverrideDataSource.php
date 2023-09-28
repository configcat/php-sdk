<?php

declare(strict_types=1);

namespace ConfigCat\Override;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes an override data source.
 */
abstract class OverrideDataSource implements LoggerAwareInterface
{
    protected LoggerInterface $logger;

    /**
     * Gets the overrides.
     *
     * @return mixed[] the overrides
     */
    abstract public function getOverrides(): array;

    /**
     * Sets the logger.
     *
     * @param LoggerInterface $logger the logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * Creates an override data source that reads the overrides from a file.
     *
     * @param string $filePath the path to the file
     *
     * @return OverrideDataSource the constructed data source
     */
    public static function localFile(string $filePath): OverrideDataSource
    {
        return new LocalFileDataSource($filePath);
    }

    /**
     * Creates an override data source that reads the overrides from an array.
     *
     * @param array<string, mixed> $overrides the array that contains the overrides
     *
     * @return OverrideDataSource the constructed data source
     */
    public static function localArray(array $overrides): OverrideDataSource
    {
        return new ArrayDataSource($overrides);
    }
}
