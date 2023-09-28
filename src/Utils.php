<?php

declare(strict_types=1);

namespace ConfigCat;

/**
 * Contains helper utility operations.
 *
 * @internal
 */
final class Utils
{
    /**
     * Determines that a string contains another string.
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
     * @param string           $text      the text to split and trim
     * @param non-empty-string $delimiter the delimiter
     *
     * @return string[] the array of split items
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
        if (true === is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    /**
     * Returns the Unix timestamp in milliseconds.
     *
     * @return float milliseconds since epoch
     */
    public static function getUnixMilliseconds(): float
    {
        return floor(microtime(true) * 1000);
    }
}
