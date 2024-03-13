<?php

declare(strict_types=1);

namespace ConfigCat;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Contains helper utility operations.
 *
 * @internal
 */
abstract class Utils
{
    /**
     * Returns the string representation of a value.
     *
     * @param mixed $value the value
     *
     * @return string the result string
     */
    public static function getStringRepresentation(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        try {
            return (string) $value;
        } catch (Throwable) { // @phpstan-ignore-line
            return str_replace(["\r\n", "\r", "\n"], ' ', var_export($value, true));
        }
    }

    public static function numberToString(float|int $number): string
    {
        if (is_nan($number)) {
            return 'NaN';
        }
        if (is_infinite($number)) {
            return $number > 0 ? 'Infinity' : '-Infinity';
        }
        if (!$number) {
            return '0';
        }

        $abs = abs($number);
        if (1e-6 <= $abs && $abs < 1e21) {
            $exp = 0;
        } else {
            $exp = self::getExponent($abs);
            $number /= pow(10, $exp);
        }

        // NOTE: number_format can't really deal with 17 decimal places,
        // e.g. number_format(0.1, 17, '.', '') results in '0.10000000000000001'.
        // So we need to manually calculate the actual number of significant decimals.
        $decimals = self::getSignificantDecimals($number);

        $str = number_format($number, $decimals, '.', '');
        if ($exp) {
            $str .= ($exp > 0 ? 'e+' : 'e').number_format($exp, 0, '.', '');
        }

        return $str;
    }

    public static function numberFromString(string $str): false|float
    {
        $str = trim($str);

        switch ($str) {
            case 'Infinity':
            case '+Infinity':
                return INF;

            case '-Infinity':
                return -INF;

            case 'NaN':
                return NAN;

            default:
                return filter_var($str, FILTER_VALIDATE_FLOAT);
        }
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

    public static function dateTimeToUnixTimeSeconds(DateTimeInterface $dateTime): ?float
    {
        $timestamp = (float) $dateTime->format('U\.v');

        // Allow values only between 0001-01-01T00:00:00.000Z and 9999-12-31T23:59:59.999
        return $timestamp < -62135596800 || 253402300800 <= $timestamp ? null : $timestamp;
    }

    public static function dateTimeFromUnixTimeSeconds(float $timestamp): ?DateTimeInterface
    {
        // Allow values only between 0001-01-01T00:00:00.000Z and 9999-12-31T23:59:59.999
        if ($timestamp < -62135596800 || 253402300800 <= $timestamp) {
            return null;
        }

        $dateTime = DateTimeImmutable::createFromFormat('U\\.v', sprintf('%1.3F', $timestamp));
        if (!$dateTime) {
            return null;
        }

        return $dateTime;
    }

    public static function formatDateTimeISO(DateTimeInterface $dateTime): string
    {
        $timeOffset = $dateTime->getOffset();

        return $dateTime->format($timeOffset ? 'Y-m-d\\TH:i:s.uP' : 'Y-m-d\\TH:i:s.u\Z');
    }

    public static function isStringList(mixed $value): bool
    {
        return is_array($value) && !self::array_some($value, function ($value, $key, $i) {
            return $key !== $i || !is_string($value);
        });
    }

    /**
     * @param list<string>               $items
     * @param null|callable(int): string $getOmittedItemsText
     */
    public static function formatStringList(array $items, int $maxCount = 0, ?callable $getOmittedItemsText = null, string $separator = ', '): string
    {
        $count = count($items);
        if (!$count) {
            return '';
        }

        $appendix = '';

        if ($maxCount > 0 && $count > $maxCount) {
            $items = array_slice($items, 0, $maxCount);
            if ($getOmittedItemsText) {
                $appendix = $getOmittedItemsText($count - $maxCount);
            }
        }

        return "'".join("'".$separator."'", $items)."'".$appendix;
    }

    /**
     * @param mixed[]                                         $array   the array to check
     * @param callable(mixed, int|string, int, mixed[]): bool $isMatch a function to execute for each element in the array; it should return a truthy value to indicate the element passes the test, and a falsy value otherwise
     *
     * @return bool `false` unless `$isMatch` returns a truthy value for an array element, in which case true is immediately returned
     */
    private static function array_some(array $array, callable $isMatch): bool
    {
        $i = 0;
        foreach ($array as $key => $value) {
            if ($isMatch($value, $key, $i, $array)) {
                return true;
            }
            ++$i;
        }

        return false;
    }

    private static function getExponent(float|int $abs): int
    {
        $exp = log10($abs);
        $ceil = ceil($exp);

        return (int) (abs($exp - $ceil) < PHP_FLOAT_EPSILON ? $ceil : floor($exp));
    }

    // Based on: https://stackoverflow.com/a/31888253/8656352
    private static function getSignificantDecimals(float|int $number): int
    {
        if (!$number) {
            return 0;
        }

        $number = abs($number);
        $exp = min(0, self::getExponent($number));

        for (; $exp > -17; --$exp) {
            $fracr = round($number, -$exp, PHP_ROUND_HALF_UP);
            // NOTE: PHP_FLOAT_EPSILON is the same as JavaScript's Number.EPSILON
            // (https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Number/EPSILON).
            if (abs($number - $fracr) < $number * 10.0 * PHP_FLOAT_EPSILON) {
                break;
            }
        }

        return min(17, -$exp);
    }
}
