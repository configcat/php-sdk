<?php

declare(strict_types=1);

namespace ConfigCat\ConfigJson;

/**
 * User Object attribute comparison operator used during the evaluation process.
 */
enum UserComparator: int
{
    /** IS ONE OF (cleartext) - It matches when the comparison attribute is equal to any of the comparison values. */
    case TEXT_IS_ONE_OF = 0;

    /** IS NOT ONE OF (cleartext) - It matches when the comparison attribute is not equal to any of the comparison values. */
    case TEXT_IS_NOT_ONE_OF = 1;

    /** CONTAINS ANY OF (cleartext) - It matches when the comparison attribute contains any comparison values as a substring. */
    case TEXT_CONTAINS_ANY_OF = 2;

    /** NOT CONTAINS ANY OF (cleartext) - It matches when the comparison attribute does not contain any comparison values as a substring. */
    case TEXT_NOT_CONTAINS_ANY_OF = 3;

    /** IS ONE OF (semver) - It matches when the comparison attribute interpreted as a semantic version is equal to any of the comparison values. */
    case SEMVER_IS_ONE_OF = 4;

    /** IS NOT ONE OF (semver) - It matches when the comparison attribute interpreted as a semantic version is not equal to any of the comparison values. */
    case SEMVER_IS_NOT_ONE_OF = 5;

    /** &lt; (semver) - It matches when the comparison attribute interpreted as a semantic version is less than the comparison value. */
    case SEMVER_LESS = 6;

    /** &lt;= (semver) - It matches when the comparison attribute interpreted as a semantic version is less than or equal to the comparison value. */
    case SEMVER_LESS_OR_EQUALS = 7;

    /** &gt; (semver) - It matches when the comparison attribute interpreted as a semantic version is greater than the comparison value. */
    case SEMVER_GREATER = 8;

    /** &gt;= (semver) - It matches when the comparison attribute interpreted as a semantic version is greater than or equal to the comparison value. */
    case SEMVER_GREATER_OR_EQUALS = 9;

    /** = (number) - It matches when the comparison attribute interpreted as a decimal number is equal to the comparison value. */
    case NUMBER_EQUALS = 10;

    /** != (number) - It matches when the comparison attribute interpreted as a decimal number is not equal to the comparison value. */
    case NUMBER_NOT_EQUALS = 11;

    /** &lt; (number) - It matches when the comparison attribute interpreted as a decimal number is less than the comparison value. */
    case NUMBER_LESS = 12;

    /** &lt;= (number) - It matches when the comparison attribute interpreted as a decimal number is less than or equal to the comparison value. */
    case NUMBER_LESS_OR_EQUALS = 13;

    /** &gt; (number) - It matches when the comparison attribute interpreted as a decimal number is greater than the comparison value. */
    case NUMBER_GREATER = 14;

    /** &gt;= (number) - It matches when the comparison attribute interpreted as a decimal number is greater than or equal to the comparison value. */
    case NUMBER_GREATER_OR_EQUALS = 15;

    /** IS ONE OF (hashed) - It matches when the comparison attribute is equal to any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_IS_ONE_OF = 16;

    /** IS NOT ONE OF (hashed) - It matches when the comparison attribute is not equal to any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_IS_NOT_ONE_OF = 17;

    /** BEFORE (UTC datetime) - It matches when the comparison attribute interpreted as the seconds elapsed since <see href="https://en.wikipedia.org/wiki/Unix_time">Unix Epoch</see> is less than the comparison value. */
    case DATETIME_BEFORE = 18;

    /** AFTER (UTC datetime) - It matches when the comparison attribute interpreted as the seconds elapsed since <see href="https://en.wikipedia.org/wiki/Unix_time">Unix Epoch</see> is greater than the comparison value. */
    case DATETIME_AFTER = 19;

    /** EQUALS (hashed) - It matches when the comparison attribute is equal to the comparison value (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_EQUALS = 20;

    /** NOT EQUALS (hashed) - It matches when the comparison attribute is not equal to the comparison value (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_NOT_EQUALS = 21;

    /** STARTS WITH ANY OF (hashed) - It matches when the comparison attribute starts with any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_STARTS_WITH_ANY_OF = 22;

    /** NOT STARTS WITH ANY OF (hashed) - It matches when the comparison attribute does not start with any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_NOT_STARTS_WITH_ANY_OF = 23;

    /** ENDS WITH ANY OF (hashed) - It matches when the comparison attribute ends with any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_ENDS_WITH_ANY_OF = 24;

    /** NOT ENDS WITH ANY OF (hashed) - It matches when the comparison attribute does not end with any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_TEXT_NOT_ENDS_WITH_ANY_OF = 25;

    /** ARRAY CONTAINS ANY OF (hashed) - It matches when the comparison attribute interpreted as a comma-separated list contains any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_ARRAY_CONTAINS_ANY_OF = 26;

    /** ARRAY NOT CONTAINS ANY OF (hashed) - It matches when the comparison attribute interpreted as a comma-separated list does not contain any of the comparison values (where the comparison is performed using the salted SHA256 hashes of the values). */
    case SENSITIVE_ARRAY_NOT_CONTAINS_ANY_OF = 27;

    /** EQUALS (cleartext) - It matches when the comparison attribute is equal to the comparison value. */
    case TEXT_EQUALS = 28;

    /** NOT EQUALS (cleartext) - It matches when the comparison attribute is not equal to the comparison value. */
    case TEXT_NOT_EQUALS = 29;

    /** STARTS WITH ANY OF (cleartext) - It matches when the comparison attribute starts with any of the comparison values. */
    case TEXT_STARTS_WITH_ANY_OF = 30;

    /** NOT STARTS WITH ANY OF (cleartext) - It matches when the comparison attribute does not start with any of the comparison values. */
    case TEXT_NOT_STARTS_WITH_ANY_OF = 31;

    /** ENDS WITH ANY OF (cleartext) - It matches when the comparison attribute ends with any of the comparison values. */
    case TEXT_ENDS_WITH_ANY_OF = 32;

    /** NOT ENDS WITH ANY OF (cleartext) - It matches when the comparison attribute does not end with any of the comparison values. */
    case TEXT_NOT_ENDS_WITH_ANY_OF = 33;

    /** ARRAY CONTAINS ANY OF (cleartext) - It matches when the comparison attribute interpreted as a comma-separated list contains any of the comparison values. */
    case ARRAY_CONTAINS_ANY_OF = 34;

    /** ARRAY NOT CONTAINS ANY OF (cleartext) - It matches when the comparison attribute interpreted as a comma-separated list does not contain any of the comparison values. */
    case ARRAY_NOT_CONTAINS_ANY_OF = 35;
}
