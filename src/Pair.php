<?php

namespace ConfigCat;

/**
 * Represents a simple key-value pair.
 */
class Pair
{
    /**
     * Creates a new Pair.
     */
    public function __construct(private readonly string $key, private readonly mixed $value)
    {
    }

    /**
     * Gets the key.
     */
    public function getKey(): string
    {
        return $this->key;
    }

    /**
     * Gets the value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
