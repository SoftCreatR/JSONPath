<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use ArrayObject;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use PHPUnit\Framework\TestCase;

class JSONPathIntegrationTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testArrayObjectTraversal(): void
    {
        $data = new ArrayObject([
            'items' => new ArrayObject([
                ['name' => 'keep', 'active' => true],
                ['name' => 'skip', 'active' => false],
            ]),
        ]);

        $result = new JSONPath($data)->find('$.items[?(@.active==true)]')->getData();

        self::assertSame([['name' => 'keep', 'active' => true]], $result);
    }

    /**
     * @throws JSONPathException
     */
    public function testDashedIndexIsParsedWithoutQuotes(): void
    {
        $data = ['data' => ['dash-key' => 42, 'other' => 1]];

        $result = new JSONPath($data)->find('$.data[dash-key]')->getData();

        self::assertSame([42], $result);
    }

    /**
     * @throws JSONPathException
     */
    public function testSlicesResolveViaPublicApi(): void
    {
        $path = new JSONPath(['values' => [0, 1, 2, 3, 4]]);

        self::assertSame([1, 2, 3], $path->find('$.values[1:-1]')->getData());
        self::assertSame([4, 3], $path->find('$.values[-1:-3:-1]')->getData());
    }
}
