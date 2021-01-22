<?php

namespace ConfigCat\Tests;

use ConfigCat\ConfigCatClient;
use ConfigCat\Log\LogLevel;
use ConfigCat\User;
use Monolog\Handler\ErrorLogHandler;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;

class RolloutIntegrationsTest extends TestCase
{
    const valueKind = 0;
    const variationKind = 1;

    /**
     * @param $file
     * @param $sdkKey
     * @param $kind
     *
     * @dataProvider rolloutTestData
     */
    public function testRolloutIntegration($file, $sdkKey, $kind)
    {
        $rows = self::readCsv("tests/" . $file);
        $settingKeys = array_slice($rows[0], 4);
        $customKey = $rows[0][3];
        $client = new ConfigCatClient($sdkKey, [
            "logger" => new Logger("ConfigCat", [new ErrorLogHandler(ErrorLogHandler::OPERATING_SYSTEM)]),
            "log-level" => LogLevel::WARNING
        ]);

        $errors = [];

        $keys = $client->getAllKeys();
        $diff = array_diff($settingKeys, $keys);
        if (!empty($diff)) {
            array_push($errors, sprintf("Not all keys are found, Expected: %s, Result: %s, Diff: %s",
                print_r($settingKeys, true),
                print_r($keys, true),
                print_r($diff, true)));
        }

        foreach (range(1, count($rows) - 1) as $i) {
            $testObjects = $rows[$i];

            $user = null;
            if ($testObjects[0] !== "##null##") {
                $identifier = $testObjects[0];

                $email = "";
                $country = "";

                if (!empty($testObjects[1]) && $testObjects[1] !== "##null##") {
                    $email = $testObjects[1];
                }

                if (!empty($testObjects[2]) && $testObjects[2] !== "##null##") {
                    $country = $testObjects[2];
                }

                $custom = [];
                if (!empty($testObjects[3]) && $testObjects[3] !== "##null##") {
                    $custom[$customKey] = $testObjects[3];
                } elseif (is_numeric($testObjects[3])) {
                    $custom[$customKey] = $testObjects[3];
                }

                $user = new User($identifier, $email, $country, $custom);
            }

            $count = 0;
            foreach ($settingKeys as $key) {
                $expected = $testObjects[$count + 4];
                $actual = $kind == self::valueKind
                    ? $client->getValue($key, null, $user)
                    : $client->getVariationId($key, null, $user);

                if (is_bool($actual)) {
                    $actual = $actual ? "True" : "False";
                }

                if (is_int($actual)) {
                    $expected = intval($expected);
                }

                if (is_double($actual)) {
                    $expected = floatval($expected);
                }

                if ($expected !== $actual) {
                    array_push($errors, sprintf("Identifier: %s, SettingKey: %s, UV: %s, Expected: %s, Result: %s", $testObjects[0], $key, $testObjects[3], $expected, $actual));
                }
                $count++;
            }
        }

        $this->assertEquals(0, count($errors));
    }

    private static function readCsv($file, $delimiter = ';')
    {
        $rows = [];
        if (($handle = fopen($file, "r")) !== false) {
            while (($data = fgetcsv($handle, 1200, $delimiter)) !== false) {
                array_push($rows, $data);
            }
            fclose($handle);
        }

        return $rows;
    }

    public function rolloutTestData()
    {
        return [
            ["testmatrix.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/psuH7BGHoUmdONrzzUOY7A", self::valueKind],
            ["testmatrix_semantic.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/BAr3KgLTP0ObzKnBTo5nhA", self::valueKind],
            ["testmatrix_number.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/uGyK3q9_ckmdxRyI7vjwCw", self::valueKind],
            ["testmatrix_semantic_2.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/q6jMCFIp-EmuAfnmZhPY7w", self::valueKind],
            ["testmatrix_sensitive.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/qX3TP2dTj06ZpCCT1h_SPA", self::valueKind],
            ["testmatrix_variationId.csv", "PKDVCLf-Hq-h-kCzMp-L7Q/nQ5qkhRAUEa6beEyyrVLBA", self::variationKind],
        ];
    }
}
