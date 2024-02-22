<?php

namespace ConfigCat\Tests;

use ConfigCat\ClientOptions;
use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use ConfigCat\Tests\Helpers\Utils;
use ConfigCat\User;
use PHPUnit\Framework\TestCase;

class RolloutIntegrationsTest extends TestCase
{
    const valueKind = 0;
    const variationKind = 1;

    /**
     * @dataProvider rolloutTestData
     *
     * @param mixed $file
     * @param mixed $sdkKey
     * @param mixed $kind
     */
    public function testRolloutIntegration($file, $sdkKey, $kind)
    {
        $rows = self::readCsv('tests/data/'.$file);
        $settingKeys = array_slice($rows[0], 4);
        $customKey = $rows[0][3];
        $client = new ConfigCatClient($sdkKey, [
            ClientOptions::LOG_LEVEL => LogLevel::WARNING,
        ]);

        $errors = [];

        $keys = $client->getAllKeys();
        $diff = array_diff($settingKeys, $keys);
        if (!empty($diff)) {
            $errors[] = sprintf(
                'Not all keys are found, Expected: %s, Result: %s, Diff: %s',
                print_r($settingKeys, true),
                print_r($keys, true),
                print_r($diff, true)
            );
        }

        foreach (range(1, count($rows) - 1) as $i) {
            $testObjects = $rows[$i];

            $user = null;
            if ('##null##' !== $testObjects[0]) {
                $identifier = $testObjects[0];

                $email = null;
                $country = null;

                if (!empty($testObjects[1]) && '##null##' !== $testObjects[1]) {
                    $email = $testObjects[1];
                }

                if (!empty($testObjects[2]) && '##null##' !== $testObjects[2]) {
                    $country = $testObjects[2];
                }

                $custom = [];
                if (!empty($testObjects[3]) && '##null##' !== $testObjects[3]) {
                    $custom[$customKey] = $testObjects[3];
                } elseif (is_numeric($testObjects[3])) {
                    $custom[$customKey] = $testObjects[3];
                }

                $user = new User($identifier, $email, $country, $custom);
            }

            $count = 0;
            foreach ($settingKeys as $key) {
                $expected = $testObjects[$count + 4];
                $actual = self::valueKind == $kind
                    ? $client->getValue($key, null, $user)
                    : $client->getValueDetails($key, null, $user)->getVariationId();

                if (is_bool($actual)) {
                    $actual = $actual ? 'True' : 'False';
                } elseif (is_int($actual)) {
                    $expected = intval($expected);
                } elseif (is_double($actual)) {
                    $expected = floatval($expected);
                } elseif ($expected !== $actual) {
                    $errors[] = sprintf('Identifier: %s, SettingKey: %s, UV: %s, Expected: %s, Result: %s', $testObjects[0], $key, $testObjects[3], $expected, $actual);
                }
                ++$count;
            }
        }

        $this->assertEquals(0, count($errors));
    }

    public function rolloutTestData(): array
    {
        return [
            // *** Config V1 ***
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d62463-86ec-8fde-f5b5-1c5c426fc830/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/psuH7BGHoUmdONrzzUOY7A', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d745f1-f315-7daf-d163-5541d3786e6f/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_semantic.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/BAr3KgLTP0ObzKnBTo5nhA', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d747f0-5986-c2ef-eef3-ec778e32e10a/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_number.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/uGyK3q9_ckmdxRyI7vjwCw', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d77fa1-a796-85f9-df0c-57c448eb9934/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_semantic_2.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/q6jMCFIp-EmuAfnmZhPY7w', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d7b724-9285-f4a7-9fcd-00f64f1e83d5/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_sensitive.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/qX3TP2dTj06ZpCCT1h_SPA', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d9f207-6883-43e5-868c-cbf677af3fe6/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_segments_old.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/LcYz135LE0qbcacz2mgXnA', self::valueKind],
            // https://app.configcat.com/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08d774b9-3d05-0027-d5f4-3e76c3dba752/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_variationId.csv', 'PKDVCLf-Hq-h-kCzMp-L7Q/nQ5qkhRAUEa6beEyyrVLBA', self::variationKind],

            // *** Config V2 ***
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-1927-4d6b-8fb9-b1472564e2d3/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/AG6C1ngVb0CvM07un6JisQ', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-278c-4f83-8d36-db73ad6e2a3a/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_semantic.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/iV8vH2MBakKxkFZylxHmTg', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-0fa3-48d0-8de8-9de55b67fb8b/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_number.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/FCWN-k1dV0iBf8QZrDgjdw', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-2b2b-451e-8359-abdef494c2a2/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_semantic_2.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/U8nt3zEhDEO5S2ulubCopA', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-2d62-4e1b-884b-6aa237b34764/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_sensitive.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/-0YmVOUNgEGKkgRF-rU65g', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbd6ca-a85f-4ed0-888a-2da18def92b5/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_segments_old.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/y_ZB7o-Xb0Swxth-ZlMSeA', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08d5a03c-feb7-af1e-a1fa-40b3329f8bed/08dbc4dc-30c6-4969-8e4c-03f6a8764199/244cf8b0-f604-11e8-b543-f23c917f9d8d
            ['testmatrix_variationId.csv', 'configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/spQnkRTIPEWVivZkWM84lQ', self::variationKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbc325-9d5e-4988-891c-fd4a45790bd1/08dbc325-9ebd-4587-8171-88f76a3004cb
            ['testmatrix_and_or.csv', 'configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/ByMO9yZNn02kXcm72lnY1A', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbc325-9a6b-4947-84e2-91529248278a/08dbc325-9ebd-4587-8171-88f76a3004cb
            ['testmatrix_comparators_v6.csv', 'configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/OfQqcTjfFUGBwMKqtyEOrQ', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbc325-9b74-45cb-86d0-4d61c25af1aa/08dbc325-9ebd-4587-8171-88f76a3004cb
            ['testmatrix_prerequisite_flag.csv', 'configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/JoGwdqJZQ0K2xDy7LnbyOg', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbc325-9cfb-486f-8906-72a57c693615/08dbc325-9ebd-4587-8171-88f76a3004cb
            ['testmatrix_segments.csv', 'configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/h99HYXWWNE2bH8eWyLAVMA', self::valueKind],
            // https://app.configcat.com/v2/e7a75611-4256-49a5-9320-ce158755e3ba/08dbc325-7f69-4fd4-8af4-cf9f24ec8ac9/08dbd63c-9774-49d6-8187-5f2aab7bd606/08dbc325-9ebd-4587-8171-88f76a3004cb
            ['testmatrix_unicode.csv', 'configcat-sdk-1/JcPbCGl_1E-K9M-fJOyKyQ/Da6w8dBbmUeMUBhh0iEeQQ', self::valueKind],
        ];
    }

    public function provideTestDataForSpecialCharactersWorks()
    {
        return Utils::withDescription([
            ['specialCharacters', 'äöüÄÖÜçéèñışğâ¢™✓😀', 'äöüÄÖÜçéèñışğâ¢™✓😀'],
            ['specialCharactersHashed', 'äöüÄÖÜçéèñışğâ¢™✓😀', 'äöüÄÖÜçéèñışğâ¢™✓😀'],
        ], function ($testCase) {
            return "settingKey: {$testCase[0]} | userId: {$testCase[1]}";
        });
    }

    /**
     * @dataProvider provideTestDataForSpecialCharactersWorks
     */
    public function testSpecialCharactersWorks(
        string $settingKey,
        string $userId,
        string $expectedReturnValue
    ) {
        $client = new ConfigCatClient('configcat-sdk-1/PKDVCLf-Hq-h-kCzMp-L7Q/u28_1qNyZ0Wz-ldYHIU7-g');

        $user = new User($userId);

        $actualReturnValue = $client->getValue($settingKey, null, $user);

        $this->assertSame($expectedReturnValue, $actualReturnValue);
    }

    private static function readCsv($file): array
    {
        $rows = [];
        if (($handle = fopen($file, 'r')) !== false) {
            while (($data = fgetcsv($handle, 1200, ';')) !== false) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        return $rows;
    }
}
