<?php

declare(strict_types=1);

namespace ConfigCat;

use Stringable;

/**
 * An object containing attributes to properly identify a given user for rollout evaluation.
 */
final class User implements Stringable
{
    public const IDENTIFIER_ATTRIBUTE = 'Identifier';
    public const EMAIL_ATTRIBUTE = 'Email';
    public const COUNTRY_ATTRIBUTE = 'Country';

    /**
     * @internal
     */
    public const WELL_KNOWN_ATTRIBUTES = [self::IDENTIFIER_ATTRIBUTE, self::EMAIL_ATTRIBUTE, self::COUNTRY_ATTRIBUTE];

    /**
     * User constructor.
     *
     * @param string                $identifier the unique identifier of the user or session (e.g. email address, primary key, session ID, etc.)
     * @param ?string               $email      email address of the user
     * @param ?string               $country    country of the user
     * @param ?array<string, mixed> $custom     custom attributes of the user for advanced targeting rule definitions (e.g. user role, subscription type, etc.)
     *
     * All comparators support `string` values as User Object attribute (in some cases they need to be provided in a specific format though, see below),
     * but some of them also support other types of values. It depends on the comparator how the values will be handled. The following rules apply:
     *
     * **Text-based comparators** (EQUALS, IS ONE OF, etc.)
     * * accept `string` values,
     * * all other values are automatically converted to `string` (a warning will be logged but evaluation will continue as normal).
     *
     * **SemVer-based comparators** (IS ONE OF, &lt;, &gt;=, etc.)
     * * accept `string` values containing a properly formatted, valid semver value,
     * * all other values are considered invalid (a warning will be logged and the currently evaluated targeting rule will be skipped).
     *
     * **Number-based comparators** (=, &lt;, &gt;=, etc.)
     * * accept `int` or `float` values,
     * * accept `string` values containing a properly formatted, valid `int` or `float` value,
     * * all other values are considered invalid (a warning will be logged and the currently evaluated targeting rule will be skipped).
     *
     * **Date time-based comparators** (BEFORE / AFTER)
     * * accept `DateTimeInterface` values, which are automatically converted to a second-based Unix timestamp,
     * * accept `int` or `float` values representing a second-based Unix timestamp,
     * * accept `string` values containing a properly formatted, valid `int` or `float` value,
     * * all other values are considered invalid (a warning will be logged and the currently evaluated targeting rule will be skipped).
     *
     * **String array-based comparators** (ARRAY CONTAINS ANY OF / ARRAY NOT CONTAINS ANY OF)
     * * accept arrays of `string`,
     * * accept `string` values containing a valid JSON string which can be deserialized to an array of `string`,
     * * all other values are considered invalid (a warning will be logged and the currently evaluated targeting rule will be skipped).
     */
    public function __construct(
        private string $identifier,
        private ?string $email = null,
        private ?string $country = null,
        private ?array $custom = null
    ) {}

    /**
     * @return string the string representation of the user
     */
    public function __toString(): string
    {
        $result = json_encode($this->getAllAttributes(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return false !== $result ? $result : '<serialization error>';
    }

    /**
     * Gets the identifier of the user.
     *
     * @return string the identifier of the user
     */
    public function getIdentifier(): string
    {
        return $this->identifier ?? '';
    }

    /**
     * Gets a user attribute identified by the given key.
     *
     * @param string $key the key of the user attribute
     *
     * @return mixed the user attribute, or null if it doesn't exist
     */
    public function getAttribute(string $key): mixed
    {
        switch ($key) {
            case self::IDENTIFIER_ATTRIBUTE: return $this->getIdentifier();

            case self::EMAIL_ATTRIBUTE: return $this->email;

            case self::COUNTRY_ATTRIBUTE: return $this->country;

            default: return $this->custom[$key] ?? null;
        }
    }

    /**
     * Gets all user attributes.
     *
     * @return array<string, mixed>
     */
    public function getAllAttributes(): array
    {
        $result = [];

        $result[self::IDENTIFIER_ATTRIBUTE] = $this->getIdentifier();

        if (isset($this->email)) {
            $result[self::EMAIL_ATTRIBUTE] = $this->email;
        }

        if (isset($this->country)) {
            $result[self::COUNTRY_ATTRIBUTE] = $this->country;
        }

        if (isset($this->custom)) {
            foreach ($this->custom as $attributeName => $attributeValue) {
                if (isset($attributeValue) && !in_array($attributeName, self::WELL_KNOWN_ATTRIBUTES, true)) {
                    $result[$attributeName] = $attributeValue;
                }
            }
        }

        return $result;
    }
}
