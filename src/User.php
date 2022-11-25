<?php

namespace ConfigCat;

use JsonException;

/**
 * An object containing attributes to properly identify a given user for rollout evaluation.
 */
final class User implements \Stringable
{
    private array $attributes = [];
    private readonly string $identifier;

    /**
     * User constructor.
     *
     * @param string $identifier the identifier of the user
     * @param string $email      Optional. The email of the user.
     * @param string $country    Optional. The country attribute of the user.
     * @param array  $custom     custom user attributes
     */
    public function __construct(string $identifier, string $email = '', string $country = '', array $custom = [])
    {
        $this->identifier = $this->attributes['Identifier'] = $identifier;

        if (!empty($email)) {
            $this->attributes['Email'] = $email;
        }

        if (!empty($country)) {
            $this->attributes['Country'] = $country;
        }

        if (!empty($custom)) {
            $this->attributes = array_merge($this->attributes, $custom);
        }
    }

    /**
     * Gets the identifier of the user.
     *
     * @return string the identifier of the user
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Gets a user attribute identified by the given key.
     *
     * @param string $key the key of the user attribute
     *
     * @return string|null the user attribute, or null if it doesn't exist
     */
    public function getAttribute(string $key): ?string
    {
        return $this->attributes[$key] ?? null;
    }

    /**
     * @return string the string representation of the user
     *
     * @throws JsonException
     */
    public function __toString(): string
    {
        return json_encode($this->attributes, \JSON_THROW_ON_ERROR);
    }
}
