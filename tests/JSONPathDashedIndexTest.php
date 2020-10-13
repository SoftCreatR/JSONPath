<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */
declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Exception;
use PHPUnit\Framework\TestCase;
use Flow\JSONPath\JSONPath;

class JSONPathDashedIndexTest extends TestCase
{
    /**
     * @return array[]
     */
    public function indexDataProvider(): array
    {
        return [
            // path, data, expected
            [
                '$.data[test-test-test]',
                [
                    'data' => [
                        'test-test-test' => 'foo'
                    ]
                ],
                [
                    'foo'
                ]
            ],
            [
                '$.data[40f35757-2563-4790-b0b1-caa904be455f]',
                [
                    'data' => [
                        '40f35757-2563-4790-b0b1-caa904be455f' => 'bar'
                    ]
                ],
                [
                    'bar'
                ]
            ]
        ];
    }

    /**
     * @dataProvider indexDataProvider
     * @param string $path
     * @param array $data
     * @param array $expected
     * @throws Exception
     */
    public function testSlice(string $path, array $data, array $expected): void
    {
        $jsonPath = new JSONPath($data);
        $result = $jsonPath->find($path)->getData();

        self::assertEquals($expected, $result);
    }
}
