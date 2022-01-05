<?php

namespace ConfigCat\Override;

use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes an override data source.
 * @package ConfigCat
 */
abstract class OverrideDataSource implements LoggerAwareInterface
{
    /** @var int */
    private $behaviour;
    /** @var LoggerInterface */
    protected $logger;

    protected function __construct($behaviour)
    {
        if (!OverrideBehaviour::isValid($behaviour)) {
            throw new InvalidArgumentException("The behaviour argument is not valid.");
        }

        $this->behaviour = $behaviour;
    }

    /**
     * Gets the overrides.
     * @return array The overrides.
     */
    abstract public function getOverrides();

    /**
     * Gets the override behaviour.
     * @return int The override behaviour.
     */
    public function getBehaviour()
    {
        return $this->behaviour;
    }

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
     * @param $behaviour int It can be used to set preference on whether the local values should
     *                       override the remote values, or use local values only when a remote value doesn't exist,
     *                       or use it for local only mode.
     * @return OverrideDataSource The constructed data source.
     */
    public static function localFile($filePath, $behaviour)
    {
        return new LocalFileDataSource($filePath, $behaviour);
    }

    /**
     * Creates an override data source that reads the overrides from an array.
     * @param $overrides array The array that contains the overrides.
     * @param $behaviour int It can be used to set preference on whether the local values should
     *                       override the remote values, or use local values only when a remote value doesn't exist,
     *                       or use it for local only mode.
     * @return OverrideDataSource The constructed data source.
     */
    public static function localArray($overrides, $behaviour)
    {
        return new ArrayDataSource($overrides, $behaviour);
    }
}
