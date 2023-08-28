<?php

namespace ConfigCat;

/**
 * Contains helper utility operations.
 * @package ConfigCat
 * @internal
 */
final class Utils
{
    /**
     * Determines that a string contains another string.
     *
     * @param string $haystack The string in we search for the other.
     * @param string $needle The string we search.
     * @return bool True if the $haystack contains the $needle.
     */
    public static function strContains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Splits a given string and trims the result items.
     *
     * @param string $text The text to split and trim.
     * @param string $delimiter The delimiter.
     * @return array The array of split items.
     */
    public static function splitTrim(string $text, string $delimiter = ','): array
    {
        return array_map('trim', explode($delimiter, $text));
    }

    /**
     * Returns the string representation of a value.
     *
     * @param mixed $value The value.
     * @return string The result string.
     */
    public static function getStringRepresentation(mixed $value): string
    {
        if (is_bool($value) === true) {
            return $value ? "true" : "false";
        }

        return (string)$value;
    }

    /**
     * Returns the Unix timestamp in milliseconds.
     *
     * @return float Milliseconds since epoch.
     */
    public static function getUnixMilliseconds(): float {
        return floor(microtime(true) * 1000);
    }
}
