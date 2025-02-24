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
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use stdClass;

class OverrideTest extends TestCase
{
    /**
     * @dataProvider provideOverrideValueTypeMismatchShouldBeHandledCorrectly_Dictionary
     */
    public function testOverrideValueTypeMismatchShouldBeHandledCorrectlyDictionary(mixed $overrideValue, mixed $defaultValue, mixed $expectedReturnValue)
    {
        $key = 'flag';
        $overrideArray = [$key => $overrideValue];

        $fakeLogger = new FakeLogger();

        $clientOptions = [
            ClientOptions::LOG_LEVEL => LogLevel::WARNING,
            ClientOptions::LOGGER => $fakeLogger,
            ClientOptions::FLAG_OVERRIDES => new FlagOverrides(
                OverrideDataSource::localArray($overrideArray),
                OverrideBehaviour::LOCAL_ONLY
            ),
        ];

        $client = new ConfigCatClient('local-only', $clientOptions);

        $evaluationDetails = $client->getValueDetails($key, $defaultValue);
        $warnings = array_filter($fakeLogger->events, function ($event) {
            return LogLevel::WARNING === $event['level'] && 4002 === ($event['context']['event_id'] ?? null);
        });

        if (is_bool($overrideValue) || is_string($overrideValue) || is_int($overrideValue) || is_double($overrideValue)) {
            $this->assertFalse($evaluationDetails->isDefaultValue());
            $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
            $this->assertNull($evaluationDetails->getErrorMessage());
            $this->assertNull($evaluationDetails->getErrorException());

            $this->assertCount(Utils::areCompatibleValues($overrideValue, $defaultValue) ? 0 : 1, $warnings);
        } else {
            $this->assertTrue($evaluationDetails->isDefaultValue());
            $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
            $this->assertNotNull($evaluationDetails->getErrorMessage());
            $this->assertNotNull($evaluationDetails->getErrorException());

            $this->assertCount(0, $warnings);

            $errors = array_filter($fakeLogger->events, function ($event) {
                return LogLevel::ERROR === $event['level'] && 1002 === ($event['context']['event_id'] ?? null);
            });
            $this->assertCount(1, $errors);
        }
    }

    public function provideOverrideValueTypeMismatchShouldBeHandledCorrectly_Dictionary(): array
    {
        return Utils::withDescription([
            [true, false, true],
            [true, '', true],
            [true, 0, true],
            [true, 0.0, true],
            ['text', false, 'text'],
            ['text', '', 'text'],
            ['text', 0, 'text'],
            ['text', 0.0, 'text'],
            [42, false, 42],
            [42, '', 42],
            [42, 0, 42],
            [42, 0.0, 42],
            [42.0, false, 42.0],
            [42.0, '', 42.0],
            [42.0, 0, 42.0],
            [42.0, 0.0, 42.0],
            [3.14, false, 3.14],
            [3.14, '', 3.14],
            [3.14, 0, 3.14],
            [3.14, 0.0, 3.14],
            [null, false, false],
            [[], false, false],
            [new stdClass(), false, false],
            [new DateTimeImmutable(), false, false],
        ], function ($testCase) {
            $overrideValue = \ConfigCat\Utils::getStringRepresentation($testCase[0]);

            return "overrideValue: {$overrideValue} | defaultValue: {$testCase[1]}";
        });
    }

    /**
     * @dataProvider provideOverrideValueTypeMismatchShouldBeHandledCorrectly_SimplifiedConfig
     */
    public function testOverrideValueTypeMismatchShouldBeHandledCorrectlySimplifiedConfig(string $overrideValueJson, mixed $defaultValue, mixed $expectedReturnValue)
    {
        $tempFile = tmpfile();

        try {
            $tempFilePath = stream_get_meta_data($tempFile)['uri'];
            $key = 'flag';
            $overrideValue = json_decode($overrideValueJson);
            fwrite($tempFile, "{ \"flags\": { \"{$key}\": {$overrideValueJson} } }");
            rewind($tempFile);

            $fakeLogger = new FakeLogger();

            $clientOptions = [
                ClientOptions::LOG_LEVEL => LogLevel::WARNING,
                ClientOptions::LOGGER => $fakeLogger,
                ClientOptions::FLAG_OVERRIDES => new FlagOverrides(
                    OverrideDataSource::localFile($tempFilePath),
                    OverrideBehaviour::LOCAL_ONLY
                ),
            ];

            $client = new ConfigCatClient('local-only', $clientOptions);

            $evaluationDetails = $client->getValueDetails($key, $defaultValue);
            $warnings = array_filter($fakeLogger->events, function ($event) {
                return LogLevel::WARNING === $event['level'] && 4002 === ($event['context']['event_id'] ?? null);
            });

            if (is_bool($overrideValue) || is_string($overrideValue) || is_int($overrideValue) || is_double($overrideValue)) {
                $this->assertFalse($evaluationDetails->isDefaultValue());
                $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
                $this->assertNull($evaluationDetails->getErrorMessage());
                $this->assertNull($evaluationDetails->getErrorException());

                $this->assertCount(Utils::areCompatibleValues($overrideValue, $defaultValue) ? 0 : 1, $warnings);
            } else {
                $this->assertTrue($evaluationDetails->isDefaultValue());
                $this->assertSame($expectedReturnValue, $evaluationDetails->getValue());
                $this->assertNotNull($evaluationDetails->getErrorMessage());
                $this->assertNotNull($evaluationDetails->getErrorException());

                $this->assertCount(0, $warnings);

                $errors = array_filter($fakeLogger->events, function ($event) {
                    return LogLevel::ERROR === $event['level'] && 1002 === ($event['context']['event_id'] ?? null);
                });
                $this->assertCount(1, $errors);
            }
        } finally {
            fclose($tempFile);
        }
    }

    public function provideOverrideValueTypeMismatchShouldBeHandledCorrectly_SimplifiedConfig(): array
    {
        return Utils::withDescription([
            ['true', false, true],
            ['true', '', true],
            ['true', 0, true],
            ['true', 0.0, true],
            ['"text"', false, 'text'],
            ['"text"', '', 'text'],
            ['"text"', 0, 'text'],
            ['"text"', 0.0, 'text'],
            ['42', false, 42],
            ['42', '', 42],
            ['42', 0, 42],
            ['42', 0.0, 42],
            ['42.0', false, 42.0],
            ['42.0', '', 42.0],
            ['42.0', 0, 42.0],
            ['42.0', 0.0, 42.0],
            ['3.14', false, 3.14],
            ['3.14', '', 3.14],
            ['3.14', 0, 3.14],
            ['3.14', 0.0, 3.14],
            ['null', false, false],
            ['[]', false, false],
            ['{}', false, false],
        ], function ($testCase) {
            return "overrideValueJson: {$testCase[0]} | defaultValue: {$testCase[1]}";
        });
    }
}
