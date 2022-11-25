<?php

namespace ConfigCat\Override;

use InvalidArgumentException;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;

/**
 * Describes feature flag & setting overrides.
 */
class FlagOverrides implements LoggerAwareInterface
{
    private readonly int $behaviour;

    /**
     * Constructs the feature flag overrides.
     *
     * @param $dataSource OverrideDataSource The data source of the feature flag overrides
     * @param $behaviour int It can be used to set preference on whether the local values should
     *                       override the remote values, or use local values only when a remote value doesn't exist,
     *                       or use it for local only mode
     */
    public function __construct(private readonly OverrideDataSource $dataSource, int $behaviour)
    {
        if (!OverrideBehaviour::isValid($behaviour)) {
            throw new InvalidArgumentException('The behaviour argument is not valid.');
        }

        $this->behaviour = $behaviour;
    }

    /**
     * Gets the override behaviour.
     *
     * @return int the override behaviour
     */
    public function getBehaviour(): int
    {
        return $this->behaviour;
    }

    /**
     * Gets the override data source.
     *
     * @return OverrideDataSource the override data source
     */
    public function getDataSource(): OverrideDataSource
    {
        return $this->dataSource;
    }

    /**
     * Sets the logger.
     *
     * @param LoggerInterface $logger the logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->dataSource->setLogger($logger);
    }
}
