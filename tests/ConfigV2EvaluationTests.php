<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use ConfigCat\Override\FlagOverrides;
use ConfigCat\Override\OverrideBehaviour;
use ConfigCat\Override\OverrideDataSource;
use ConfigCat\Tests\Helpers\FakeLogger;
use ConfigCat\Tests\Helpers\Utils;
use ConfigCat\User;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ConfigV2EvaluationTests extends TestCase
{
    private const TEST_DATA_ROOT_PATH = 'tests/data';

    public function provideTestDataForComparisonAttributeConversionToCanonicalStringRepresentation()
    {
        return Utils::withDescription([
            ['numberToStringConversion', .12345, '1'],
            ['numberToStringConversionInt', 125, '4'],
            ['numberToStringConversionPositiveExp', -1.23456789e96, '2'],
            ['numberToStringConversionNegativeExp', -12345.6789E-100, '4'],
            ['numberToStringConversionNaN', NAN, '3'],
            ['numberToStringConversionPositiveInf', +INF, '4'],
            ['numberToStringConversionNegativeInf', -INF, '3'],
            ['dateToStringConversion', new DateTime('2023-03-31T23:59:59.9990000Z'), '3'],
            ['dateToStringConversion', new DateTimeImmutable('2023-03-31T23:59:59.9990000Z'), '3'],
            ['dateToStringConversion', 1680307199.999, '3'],
            ['dateToStringConversionNaN', NAN, '3'],
            ['dateToStringConversionPositiveInf', +INF, '1'],
            ['dateToStringConversionNegativeInf', -INF, '5'],
            ['stringArrayToStringConversion', ['read', 'Write', ' eXecute '], '4'],
            ['stringArrayToStringConversionEmpty', [], '5'],
            ['stringArrayToStringConversionSpecialChars', ["+<>%\"'\\/\t\r\n"], '3'],
            ['stringArrayToStringConversionUnicode', ['äöüÄÖÜçéèñışğâ¢™✓😀'], '2'],
        ], function ($testCase) {
            $customAttributeValue = str_replace(["\r\n", "\r", "\n"], ' ', var_export($testCase[1], true));

            return "key: {$testCase[0]} | customAttributeValue: {$customAttributeValue}";
        });
    }

    /**
     * @dataProvider provideTestDataForComparisonAttributeConversionToCanonicalStringRepresentation
     */
    public function testComparisonAttributeConversionToCanonicalStringRepresentation(string $key, mixed $customAttributeValue, string $expectedReturnValue)
    {
        $clientOptions = [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(
                OverrideDataSource::localFile(self::TEST_DATA_ROOT_PATH.'/comparison_attribute_conversion.json'),
                OverrideBehaviour::LOCAL_ONLY
            ),
        ];

        $client = new ConfigCatClient('local-only', $clientOptions);

        $user = new User('12345', null, null, [
            'Custom1' => $customAttributeValue,
        ]);

        $defaultValue = 'default';
        $actualReturnValue = $client->getValue($key, $defaultValue, $user);

        $this->assertSame($expectedReturnValue, $actualReturnValue);
    }

    public function provideTestDataForComparisonAttributeTrimming()
    {
        return Utils::withDescription([
            ['isoneof', 'no trim'],
            ['isnotoneof', 'no trim'],
            ['isoneofhashed', 'no trim'],
            ['isnotoneofhashed', 'no trim'],
            ['equalshashed', 'no trim'],
            ['notequalshashed', 'no trim'],
            ['arraycontainsanyofhashed', 'no trim'],
            ['arraynotcontainsanyofhashed', 'no trim'],
            ['equals', 'no trim'],
            ['notequals', 'no trim'],
            ['startwithanyof', 'no trim'],
            ['notstartwithanyof', 'no trim'],
            ['endswithanyof', 'no trim'],
            ['notendswithanyof', 'no trim'],
            ['arraycontainsanyof', 'no trim'],
            ['arraynotcontainsanyof', 'no trim'],
            ['startwithanyofhashed', 'no trim'],
            ['notstartwithanyofhashed', 'no trim'],
            ['endswithanyofhashed', 'no trim'],
            ['notendswithanyofhashed', 'no trim'],
            // semver comparators user values trimmed because of backward compatibility
            ['semverisoneof', '4 trim'],
            ['semverisnotoneof', '5 trim'],
            ['semverless', '6 trim'],
            ['semverlessequals', '7 trim'],
            ['semvergreater', '8 trim'],
            ['semvergreaterequals', '9 trim'],
            // number and date comparators user values trimmed because of backward compatibility
            ['numberequals', '10 trim'],
            ['numbernotequals', '11 trim'],
            ['numberless', '12 trim'],
            ['numberlessequals', '13 trim'],
            ['numbergreater', '14 trim'],
            ['numbergreaterequals', '15 trim'],
            ['datebefore', '18 trim'],
            ['dateafter', '19 trim'],
            // "contains any of" and "not contains any of" is a special case, the not trimmed user attribute checked against not trimmed comparator values.
            ['containsanyof', 'no trim'],
            ['notcontainsanyof', 'no trim'],
        ], function ($testCase) {
            return "key: {$testCase[0]}";
        });
    }

    /**
     * @dataProvider provideTestDataForComparisonAttributeTrimming
     */
    public function testComparisonAttributeTrimming(string $key, string $expectedReturnValue)
    {
        $clientOptions = [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(
                OverrideDataSource::localFile(self::TEST_DATA_ROOT_PATH.'/comparison_attribute_trimming.json'),
                OverrideBehaviour::LOCAL_ONLY
            ),
        ];

        $client = new ConfigCatClient('local-only', $clientOptions);

        $user = new User(' 12345 ', null, '[" USA "]', [
            'Version' => ' 1.0.0 ',
            'Number' => ' 3 ',
            'Date' => ' 1705253400 ',
        ]);

        $defaultValue = 'default';
        $actualReturnValue = $client->getValue($key, $defaultValue, $user);

        $this->assertSame($expectedReturnValue, $actualReturnValue);
    }

    public function provideTestDataForComparisonValueTrimming()
    {
        return Utils::withDescription([
            ['isoneof', 'no trim'],
            ['isnotoneof', 'no trim'],
            ['containsanyof', 'no trim'],
            ['notcontainsanyof', 'no trim'],
            ['isoneofhashed', 'no trim'],
            ['isnotoneofhashed', 'no trim'],
            ['equalshashed', 'no trim'],
            ['notequalshashed', 'no trim'],
            ['arraycontainsanyofhashed', 'no trim'],
            ['arraynotcontainsanyofhashed', 'no trim'],
            ['equals', 'no trim'],
            ['notequals', 'no trim'],
            ['startwithanyof', 'no trim'],
            ['notstartwithanyof', 'no trim'],
            ['endswithanyof', 'no trim'],
            ['notendswithanyof', 'no trim'],
            ['arraycontainsanyof', 'no trim'],
            ['arraynotcontainsanyof', 'no trim'],
            ['startwithanyofhashed', 'no trim'],
            ['notstartwithanyofhashed', 'no trim'],
            ['endswithanyofhashed', 'no trim'],
            ['notendswithanyofhashed', 'no trim'],
            // semver comparator values trimmed because of backward compatibility
            ['semverisoneof', '4 trim'],
            ['semverisnotoneof', '5 trim'],
            ['semverless', '6 trim'],
            ['semverlessequals', '7 trim'],
            ['semvergreater', '8 trim'],
            ['semvergreaterequals', '9 trim'],
        ], function ($testCase) {
            return "key: {$testCase[0]}";
        });
    }

    /**
     * @dataProvider provideTestDataForComparisonValueTrimming_Test
     */
    public function testComparisonValueTrimming(string $key, string $expectedReturnValue)
    {
        $clientOptions = [
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(
                OverrideDataSource::localFile(self::TEST_DATA_ROOT_PATH.'/comparison_value_trimming.json'),
                OverrideBehaviour::LOCAL_ONLY
            ),
        ];

        $client = new ConfigCatClient('local-only', $clientOptions);

        $user = new User('12345', null, '["USA"]', [
            'Version' => '1.0.0',
            'Number' => '3',
            'Date' => '1705253400',
        ]);

        $defaultValue = 'default';
        $actualReturnValue = $client->getValue($key, $defaultValue, $user);

        $this->assertSame($expectedReturnValue, $actualReturnValue);
    }

    public function testUserObjectAttributeValueConversionTextComparisons()
    {
        $fakeLogger = new FakeLogger();

        $clientOptions = [
            ClientOptions::LOG_LEVEL => LogLevel::WARNING,
            ClientOptions::LOGGER => $fakeLogger,
        ];

        $client = new ConfigCatClient('configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', $clientOptions);

        $customAttributeName = 'Custom1';
        $customAttributeValue = 42;
        $user = new User('12345', null, null, [
            $customAttributeName => $customAttributeValue,
        ]);

        $key = 'boolTextEqualsNumber';
        $evaluationDetails = $client->getValueDetails($key, null, $user);

        $this->assertSame(true, $evaluationDetails->getValue());

        $this->assertEquals(1, count($fakeLogger->events));

        $event = $fakeLogger->events[0];
        $message = FakeLogger::formatMessage($event);
        $expectedAttributeValueText = '42';
        $this->assertSame("WARNING [3005] Evaluation of condition (User.{$customAttributeName} EQUALS '{$expectedAttributeValueText}') for setting '{$key}' may not produce the expected result (the User.{$customAttributeName} attribute is not a string value, thus it was automatically converted to the string value '{$expectedAttributeValueText}'). Please make sure that using a non-string value was intended.", $message);
    }

    public function provideTestDataForUserObjectAttributeValueConversion_NonTextComparisons()
    {
        return Utils::withDescription([
            // SemVer-based comparisons
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', '0.0', '20%'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', '0.9.9', '< 1.0.0'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', '1.0.0', '20%'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', '1.1', '20%'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', 0, '20%'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', 0.9, '20%'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', 'lessThanWithPercentage', '12345', 'Custom1', 2, '20%'],
            // Number-based comparisons
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', -1, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 2, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 3, '<>4.2'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 5, '>=5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', PHP_INT_MIN, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', PHP_INT_MAX, '>5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', -INF, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', -1, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 2, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 2.1, '<=2,1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 3, '<>4.2'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 5, '>=5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', PHP_FLOAT_MIN, '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', PHP_FLOAT_MAX, '>5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', +INF, '>5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', NAN, '<>4.2'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '-Infinity', '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '-1', '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '2', '<2.1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '2.1', '<=2,1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '2,1', '<=2,1'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '3', '<>4.2'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', '5', '>=5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 'Infinity', '>5'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 'NaN', '<>4.2'],
            ['configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', 'numberWithPercentage', '12345', 'Custom1', 'NaNa', '80%'],
            // Date time-based comparisons
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-03-31T23:59:59.9990000Z'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-04-01T01:59:59.9990000+02:00'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-04-01T00:00:00.0010000Z'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-04-01T02:00:00.0010000+02:00'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-04-30T23:59:59.9990000Z'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-05-01T01:59:59.9990000+02:00'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-05-01T00:00:00.0010000Z'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTime('2023-05-01T02:00:00.0010000+02:00'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-03-31T23:59:59.9990000Z'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-04-01T01:59:59.9990000+02:00'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-04-01T00:00:00.0010000Z'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-04-01T02:00:00.0010000+02:00'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-04-30T23:59:59.9990000Z'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-05-01T01:59:59.9990000+02:00'), true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-05-01T00:00:00.0010000Z'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', new DateTimeImmutable('2023-05-01T02:00:00.0010000+02:00'), false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', -INF, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1680307199.999, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1680307200.001, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1682899199.999, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1682899200.001, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', +INF, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', NAN, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1680307199, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1680307201, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1682899199, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 1682899201, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '-Infinity', false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '1680307199.999', false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '1680307200.001', true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '1682899199.999', true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '1682899200.001', false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', '+Infinity', false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'boolTrueIn202304', '12345', 'Custom1', 'NaN', false],
            // String array-based comparisons
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'stringArrayContainsAnyOfDogDefaultCat', '12345', 'Custom1', ['x', 'read'], 'Dog'],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'stringArrayContainsAnyOfDogDefaultCat', '12345', 'Custom1', ['x', 'Read'], 'Cat'],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'stringArrayContainsAnyOfDogDefaultCat', '12345', 'Custom1', '["x", "read"]', 'Dog'],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'stringArrayContainsAnyOfDogDefaultCat', '12345', 'Custom1', '["x", "Read"]', 'Cat'],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', 'stringArrayContainsAnyOfDogDefaultCat', '12345', 'Custom1', 'x, read', 'Cat'],
        ], function ($testCase) {
            $customAttributeValue = str_replace(["\r\n", "\r", "\n"], ' ', var_export($testCase[4], true));

            return "sdkKey: {$testCase[0]} | key: {$testCase[1]} | userId: {$testCase[2]} | customAttributeName: {$testCase[3]} | customAttributeValue: {$customAttributeValue}";
        });
    }

    /**
     * @dataProvider provideTestDataForUserObjectAttributeValueConversion_NonTextComparisons
     */
    public function testUserObjectAttributeValueConversionNonTextComparisons(
        string $sdkKey,
        string $key,
        ?string $userId,
        string $customAttributeName,
        mixed $customAttributeValue,
        mixed $expectedReturnValue
    ) {
        $client = new ConfigCatClient($sdkKey);

        $user = isset($userId)
            ? new User($userId, null, null, [
                $customAttributeName => $customAttributeValue,
            ])
            : null;

        $evaluationDetails = $client->getValueDetails($key, null, $user);

        $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
    }

    public function provideTestDataForEvaluationDetailsMatchedEvaluationRuleAndPercantageOption()
    {
        // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbc325-9e4e-4f59-86b2-5da50924b6ca/08dbc325-9ebd-4587-8171-88f76a3004cb
        return Utils::withDescription([
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', null, null, null, 'Cat', false, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', null, null, 'Cat', false, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'a@example.com', null, 'Dog', true, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'a@configcat.com', null, 'Cat', false, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'a@configcat.com', '', 'Frog', true, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'a@configcat.com', 'US', 'Fish', true, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'b@configcat.com', null, 'Cat', false, false],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'b@configcat.com', '', 'Falcon', false, true],
            ['configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/P4e3fAz_1ky2-Zg2e4cbkw', 'stringMatchedTargetingRuleAndOrPercentageOption', '12345', 'b@configcat.com', 'US', 'Spider', false, true],
        ], function ($testCase) {
            return "sdkKey: {$testCase[0]} | key: {$testCase[1]} | userId: {$testCase[2]} | email: {$testCase[3]} | percentageBase: {$testCase[4]}";
        });
    }

    /**
     * @dataProvider provideTestDataForEvaluationDetailsMatchedEvaluationRuleAndPercantageOption
     */
    public function testEvaluationDetailsMatchedEvaluationRuleAndPercantageOption(
        string $sdkKey,
        string $key,
        ?string $userId,
        ?string $email,
        ?string $percentageBase,
        string $expectedReturnValue,
        bool $expectedIsExpectedMatchedTargetingRuleSet,
        bool $expectedIsExpectedMatchedPercentageOptionSet
    ) {
        $client = new ConfigCatClient($sdkKey);

        $user = isset($userId)
            ? new User($userId, $email, null, [
                'PercentageBase' => $percentageBase,
            ])
            : null;

        $evaluationDetails = $client->getValueDetails($key, null, $user);

        $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
        $this->assertSame($expectedIsExpectedMatchedTargetingRuleSet, null != $evaluationDetails->getMatchedTargetingRule());
        $this->assertSame($expectedIsExpectedMatchedPercentageOptionSet, null != $evaluationDetails->getMatchedPercentageOption());
    }
}
