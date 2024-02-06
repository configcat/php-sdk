<?php

declare(strict_types=1);

namespace ConfigCat;

use DateTimeImmutable;
use DateTimeInterface;
use LogicException;
use NumberFormatter;

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
        if (true === is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    public static function numberToString(float|int $number): string
    {
        if ($number < 1e-6) {
            $formatter = new NumberFormatter('', NumberFormatter::SCIENTIFIC);
            $formatter->setSymbol(NumberFormatter::EXPONENTIAL_SYMBOL, 'e');
        } elseif ($number >= 1e21) {
            $formatter = new NumberFormatter('', NumberFormatter::SCIENTIFIC);
            $formatter->setSymbol(NumberFormatter::EXPONENTIAL_SYMBOL, 'e+');
        } else {
            $formatter = new NumberFormatter('', NumberFormatter::DECIMAL);
        }
        $formatter->setSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL, '.');
        $formatter->setSymbol(NumberFormatter::INFINITY_SYMBOL, 'Infinity');
        $formatter->setSymbol(NumberFormatter::NAN_SYMBOL, 'NaN');
        $formatter->setAttribute(NumberFormatter::GROUPING_USED, 0);
        $formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, 0);
        $formatter->setAttribute(NumberFormatter::MAX_FRACTION_DIGITS, 17);

        $str = $formatter->format($number);

        return false !== $str ? $str : throw new LogicException();
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

    public static function dateTimeToUnixSeconds(DateTimeInterface $dateTime): ?float
    {
        $timestamp = (float) $dateTime->format('U\.v');

        // Allow values only between 0001-01-01T00:00:00.000Z and 9999-12-31T23:59:59.999
        return $timestamp < -62135596800 || 253402300800 <= $timestamp ? $timestamp : null;
    }

    public static function dateTimeFromUnixSeconds(float $timestamp): ?DateTimeInterface
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
}
