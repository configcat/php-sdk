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
}
