<?php

namespace ConfigCat;

/**
 * An object containing attributes to properly identify a given user for rollout evaluation.
 * @package ConfigCat
 */
final class User
{
    private string $identifier;
    private array $attributes = [];

    /**
     * User constructor.
     *
     * @param string $identifier The identifier of the user.
     * @param string $email Optional. The email of the user.
     * @param string $country Optional. The country attribute of the user.
     * @param array $custom Custom user attributes.
     */
    public function __construct(
        string $identifier,
        string $email = "",
        string $country = "",
        array $custom = []
    ) {
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
     * @return string The identifier of the user.
     */
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    /**
     * Gets a user attribute identified by the given key.
     *
     * @param string $key The key of the user attribute.
     * @return string|null The user attribute, or null if it doesn't exist.
     */
    public function getAttribute(string $key): ?string
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }

    /**
     * @return string The string representation of the user.
     */
    public function __toString(): string
    {
        return json_encode($this->attributes);
    }
}
