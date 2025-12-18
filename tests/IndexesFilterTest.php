<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\IndexesFilter;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexesFilter::class)]
class IndexesFilterTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testReturnsSliceAndExplicitIndexes(): void
    {
        $token = new JSONPathToken(TokenType::Indexes, [
            ['type' => 'slice', 'value' => ['start' => 1, 'end' => 3, 'step' => null]],
            0,
        ]);

        $filter = new IndexesFilter($token);

        self::assertSame([2, 3, 1], $filter->filter([1, 2, 3, 4]));
    }

    /**
     * @throws JSONPathException
     */
    public function testSupportsQueryAndWildcard(): void
    {
        $token = new JSONPathToken(TokenType::Indexes, [
            ['type' => 'query', 'value' => '@.v>1'],
            '*',
        ]);

        $filter = new IndexesFilter($token);
        $filter->setRootData([]);

        $data = [
            ['v' => 1],
            ['v' => 2],
        ];

        $result = $filter->filter($data);

        self::assertSame([['v' => 2], ['v' => 1], ['v' => 2]], $result);
    }
}
