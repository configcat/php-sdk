<?php

namespace ConfigCat;

use PHPUnit\Framework\TestCase;

class RolloutIntegrationsTest extends TestCase
{
    public function testRolloutIntegration()
    {
        $rows = self::readCsv("tests/testmatrix.csv");
        $settingKeys = array_slice($rows[0], 4);
        $client = new ConfigCatClient("PKDVCLf-Hq-h-kCzMp-L7Q/psuH7BGHoUmdONrzzUOY7A");

        $errors = [];
        foreach (range(1, count($rows) - 1) as $i) {
            $testObjects = $rows[$i];

            $user = null;
            if (!empty($testObjects[0]) && $testObjects[0] !== "##null##") {
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
                    $custom['Custom1'] = $testObjects[3];
                }

                $user = new User($identifier, $email, $country, $custom);
            }

            $count = 0;
            foreach ($settingKeys as $key) {
                $expected = $testObjects[$count + 4];
                $actual = $client->getValue($key, null, $user);

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
                    array_push($errors, sprintf("Identifier: %s, SettingKey: %s, Expected: %s, Result: %s", $testObjects[0], $key, $expected, $actual));
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
}