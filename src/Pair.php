<?php

namespace ConfigCat;

/**
 * Represents a simple key-value pair.
 * @package ConfigCat
 */
class Pair
{
    /**
     * Creates a new Pair.
     *
     * @param string $key The key.
     * @param mixed $value The value:
     */
    public function __construct(private readonly string $key, private readonly mixed $value)
    {
    }

    /**
     * Gets the key.
     *
     * @return string
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Gets the value.
     *
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
