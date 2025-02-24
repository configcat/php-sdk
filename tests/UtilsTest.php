<?php

namespace ConfigCat\Tests;

use ConfigCat\Tests\Helpers\Utils;
use PHPUnit\Framework\TestCase;

class UtilsTest extends TestCase
{
    /**
     * @dataProvider provideTestDataForNumberToString
     */
    public function testNumberToString(float|int $number, string $expectedReturnValue)
    {
        $this->assertSame($expectedReturnValue, \ConfigCat\Utils::numberToString($number));
    }

    public function provideTestDataForNumberToString(): array
    {
        return Utils::withDescription([
            [NAN, 'NaN'],
            [INF, 'Infinity'],
            [-INF, '-Infinity'],
            [0, '0'],
            [1, '1'],
            [-1, '-1'],
            [0.1, '0.1'],
            [-0.1, '-0.1'],
            [1e-6, '0.000001'],
            [-1e-6, '-0.000001'],
            [0.99e-6, '9.9e-7'],
            [-0.99e-6, '-9.9e-7'],
            [0.99e21, '990000000000000000000'],
            [-0.99e21, '-990000000000000000000'],
            [1e21, '1e+21'],
            [-1e21, '-1e+21'],
            [1.000000000000000056e-01, '0.1'],
            [1.199999999999999956e+00, '1.2'],
            [1.229999999999999982e+00, '1.23'],
            [1.233999999999999986e+00, '1.234'],
            [1.234499999999999931e+00, '1.2345'],
            [1.002000000000000028e+02, '100.2'],
            [1.030000000000000000e+05, '103000'],
            [1.003001000000000005e+02, '100.3001'],
            [-1.000000000000000056e-01, '-0.1'],
            [-1.199999999999999956e+00, '-1.2'],
            [-1.229999999999999982e+00, '-1.23'],
            [-1.233999999999999986e+00, '-1.234'],
            [-1.234499999999999931e+00, '-1.2345'],
            [-1.002000000000000028e+02, '-100.2'],
            [-1.030000000000000000e+05, '-103000'],
            [-1.003001000000000005e+02, '-100.3001'],
        ], function ($testCase) {
            return "number: {$testCase[0]}";
        });
    }
}
