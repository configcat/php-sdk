<?php

namespace ConfigCat\Override;

use ConfigCat\Attributes\SettingAttributes;
use InvalidArgumentException;

/**
 * Describes an array override data source.
 * @package ConfigCat
 */
class ArrayDataSource extends OverrideDataSource
{
    /** @var array */
    private $overrides;

    /**
     * Constructs an array data source.
     * @param $overrides array The array that contains the overrides.
     */
    public function __construct(array $overrides)
    {
        if (!is_array($overrides)) {
            throw new InvalidArgumentException("The overrides is not a valid array.");
        }

        $this->overrides = $overrides;
    }

    /**
     * Gets the overrides.
     * @return array The overrides.
     */
    public function getOverrides(): array
    {
        $result = [];
        foreach ($this->overrides as $key => $value) {
            $result[$key] = [
                SettingAttributes::VALUE => $value
            ];
        }
        return $result;
    }
}
