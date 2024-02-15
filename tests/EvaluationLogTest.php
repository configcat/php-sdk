<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use ConfigCat\Override\FlagOverrides;
use ConfigCat\Override\OverrideBehaviour;
use ConfigCat\Override\OverrideDataSource;
use ConfigCat\Tests\Helpers\FakeLogger;
use ConfigCat\User;
use PHPUnit\Framework\TestCase;

class EvaluationLogTest extends TestCase
{
    private const TEST_DATA_ROOT_PATH = 'tests/data/evaluationlog';

    private const TEST_SETS = [
        'simple_value',
        '1_targeting_rule',
        '2_targeting_rules',
        'options_based_on_user_id',
        'options_based_on_custom_attr',
        'options_after_targeting_rule',
        'options_within_targeting_rule',
        'and_rules',
        'segment',
        'prerequisite_flag',
        'comparators',
        'epoch_date_validation',
        'number_validation',
        'semver_validation',
        'list_truncation',
    ];

    public function provideTestData(): array
    {
        $testCaseDataArray = [];

        foreach (self::TEST_SETS as $testSetName) {
            $filePath = self::TEST_DATA_ROOT_PATH.'/'.$testSetName.'.json';
            $fileContent = file_get_contents($filePath);
            $testSet = json_decode($fileContent, true);

            $sdkKey = $testSet['sdkKey'];
            $baseUrlOrOverrideFileName = is_string($sdkKey) && '' !== $sdkKey
                ? $testSet['baseUrl'] ?? null
                : $testSet['jsonOverride'];

            $testCases = $testSet['tests'] ?? [];

            foreach ($testCases as $i => $testCase) {
                $expectedLogFileName = $testCase['expectedLog'];
                $testName = $testSetName.'['.$i.'] - '.$expectedLogFileName;
                $testCaseDataArray[$testName] = [
                    $testSetName,
                    $sdkKey,
                    $baseUrlOrOverrideFileName,
                    $testCase['key'],
                    $testCase['defaultValue'] ?? null,
                    $testCase['user'] ?? null,
                    $testCase['returnValue'] ?? null,
                    $expectedLogFileName,
                ];
            }
        }

        return $testCaseDataArray;
    }

    /**
     * @dataProvider provideTestData
     */
    public function testEvaluationLog(
        string $testSetName,
        ?string $sdkKey,
        ?string $baseUrlOrOverrideFileName,
        string $key,
        mixed $defaultValue,
        ?array $userObject,
        mixed $expectedReturnValue,
        string $expectedLogFileName
    ) {
        if (isset($userObject)) {
            $identifier = $userObject[User::IDENTIFIER_ATTRIBUTE];
            $email = $userObject[User::EMAIL_ATTRIBUTE] ?? null;
            $country = $userObject[User::COUNTRY_ATTRIBUTE] ?? null;
            $custom = null;
            foreach ($userObject as $attributeName => $attributeValue) {
                if (!in_array($attributeName, User::WELL_KNOWN_ATTRIBUTES, true)) {
                    $custom ??= [];
                    $custom[$attributeName] = $attributeValue;
                }
            }
            $user = new User($identifier, $email, $country, $custom);
        } else {
            $user = null;
        }

        $fakeLogger = new FakeLogger();

        $clientOptions = [
            ClientOptions::LOG_LEVEL => LogLevel::INFO,
            ClientOptions::LOGGER => $fakeLogger,
        ];

        if (!(is_string($sdkKey) && '' !== $sdkKey)) {
            $sdkKey = 'local-only';
            $clientOptions[ClientOptions::FLAG_OVERRIDES] = new FlagOverrides(
                OverrideDataSource::localFile(self::TEST_DATA_ROOT_PATH.'/_overrides/'.$baseUrlOrOverrideFileName),
                OverrideBehaviour::LOCAL_ONLY
            );
        } elseif (!empty($baseUrlOrOverrideFileName)) {
            $clientOptions[ClientOptions::BASE_URL] = $baseUrlOrOverrideFileName;
        }

        $client = new ConfigCatClient($sdkKey, $clientOptions);

        $actualReturnValue = $client->getValue($key, $defaultValue, $user);

        $this->assertEquals($expectedReturnValue, $actualReturnValue);

        $expectedLogFilePath = self::TEST_DATA_ROOT_PATH.'/'.$testSetName.'/'.$expectedLogFileName;
        $expectedLogText = '';
        foreach (file($expectedLogFilePath) as $line) {
            $expectedLogText .= rtrim($line, "\r\n").PHP_EOL;
        }

        $actualLogText = '';
        foreach ($fakeLogger->events as $event) {
            $actualLogText .= FakeLogger::formatMessage($event).PHP_EOL;
        }

        $this->assertEquals($expectedLogText, $actualLogText);
    }
}
