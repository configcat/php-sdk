<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * An object containing attributes to properly identify a given user for rollout evaluation.
 */
final class User
{
    private string $identifier;

    /**
     * @var array<string, string>
     */
    private array $attributes = [];

    /**
     * User constructor.
     *
     * @param string                $identifier the identifier of the user
     * @param string                $email      Optional. The email of the user.
     * @param string                $country    Optional. The country attribute of the user.
     * @param array<string, string> $custom     custom user attributes
     */
    public function __construct(
        string $identifier,
        string $email = '',
        string $country = '',
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
     * @return string the string representation of the user
     */
    public function __toString(): string
    {
        $result = json_encode($this->attributes);
        if (!$result) {
            return '';
        }

        return $result;
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
     * @return null|string the user attribute, or null if it doesn't exist
     */
    public function getAttribute(string $key): ?string
    {
        return array_key_exists($key, $this->attributes) ? $this->attributes[$key] : null;
    }
}
