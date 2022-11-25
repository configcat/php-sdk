<?php

namespace ConfigCat\Override;

use ConfigCat\Attributes\SettingAttributes;

/**
 * Describes an array override data source.
 */
class ArrayDataSource extends OverrideDataSource
{
    /**
     * Constructs an array data source.
     *
     * @param $overrides array The array that contains the overrides
     */
    public function __construct(private readonly array $overrides)
    {
    }

    /**
     * Gets the overrides.
     *
     * @return array the overrides
     */
    public function getOverrides(): array
    {
        $result = [];
        foreach ($this->overrides as $key => $value) {
            $result[$key] = [
                SettingAttributes::VALUE => $value,
            ];
        }

        return $result;
    }
}
