<?php

namespace ConfigCat;

/**
 * Class Pair represents a simple key-value pair.
 * @package ConfigCat
 */
class Pair
{
    /** @var string The key. */
    private $key;

    /** @var mixed The value. */
    private $value;

    /**
     * Creates a new Pair.
     *
     * @param string $key The key.
     * @param mixed $value The value:
     */
    public function __construct($key, $value)
    {
        $this->key = $key;
        $this->value = $value;
    }

    /**
     * Gets the key.
     *
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * Gets the value.
     *
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
