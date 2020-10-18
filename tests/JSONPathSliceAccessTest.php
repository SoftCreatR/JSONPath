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

class JSONPathSliceAccessTest extends TestCase
{
    /**
     * @return array
     */
    public function sliceDataProvider(): array
    {
        return [
            // path, data, expected
            [
                '$.data[1:3]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo1',
                    'foo2'
                ]
            ],
            [
                '$.data[4:]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo4',
                    'foo5',
                ]
            ],
            [
                '$.data[:2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo0',
                    'foo1'
                ]
            ],
            [
                '$.data[:]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo0',
                    'foo1',
                    'foo2',
                    'foo3',
                    'foo4',
                    'foo5',
                ]
            ],
            [
                '$.data[-1]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo5',
                ]
            ],
            [
                '$.data[-2:]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo4',
                    'foo5',
                ]
            ],
            [
                '$.data[:-2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo0',
                    'foo1',
                    'foo2',
                    'foo3',
                ]
            ],
            [
                '$.data[::2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo0',
                    'foo2',
                    'foo4'
                ]
            ],
            [
                '$.data[2::2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo2',
                    'foo4'
                ]
            ],
            [
                '$.data[:-2:2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo0',
                    'foo2'
                ]
            ],
            [
                '$.data[1:5:2]',
                [
                    'data' => [
                        'foo0',
                        'foo1',
                        'foo2',
                        'foo3',
                        'foo4',
                        'foo5',
                    ]
                ],
                [
                    'foo1',
                    'foo3',
                ]
            ]
        ];
    }

    /**
     * @dataProvider sliceDataProvider
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
