<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * Represents a simple key-value pair.
 */
class Pair
{
    /**
     * Creates a new Pair.
     *
     * @param string $key   the key
     * @param mixed  $value The value:
     */
    public function __construct(private readonly string $key, private readonly mixed $value) {}

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
