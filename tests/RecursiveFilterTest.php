<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\RecursiveFilter;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(RecursiveFilter::class)]
class RecursiveFilterTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testRecursesThroughNestedArraysAndObjects(): void
    {
        $token = new JSONPathToken(TokenType::Recursive, null);
        $filter = new RecursiveFilter($token);

        $nestedObject = (object)['inner' => ['value' => 3]];
        $data = ['obj' => $nestedObject, 'scalar' => 1];

        $result = $filter->filter($data);

        self::assertSame($nestedObject, $result[0]['obj']);
        self::assertSame(
            [
                ['inner' => ['value' => 3]],
                ['value' => 3],
            ],
            \array_slice($result, 1)
        );
    }
}
