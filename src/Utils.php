<?php

namespace ConfigCat;

/**
 * Contains helper utility operations.
 *
 * @internal
 */
final class Utils
{
    /**
     * Determines that a string contains an other.
     *
     * @param string $haystack the string in we search for the other
     * @param string $needle   the string we search
     *
     * @return bool true if the $haystack contains the $needle
     */
    public static function strContains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Splits a given string and trims the result items.
     *
     * @param string $text      the text to split and trim
     * @param string $delimiter the delimiter
     *
     * @return array the array of split items
     */
    public static function splitTrim(string $text, string $delimiter = ','): array
    {
        return array_map('trim', explode($delimiter, $text));
    }

    /**
     * Returns the string representation of a value.
     *
     * @param mixed $value the value
     *
     * @return string the result string
     */
    public static function getStringRepresentation(mixed $value): string
    {
        if (\is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
