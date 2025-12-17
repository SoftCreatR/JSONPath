<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\IndexesFilter;
use Flow\JSONPath\Filters\IndexFilter;
use Flow\JSONPath\Filters\QueryMatchFilter;
use Flow\JSONPath\Filters\QueryResultFilter;
use Flow\JSONPath\Filters\RecursiveFilter;
use Flow\JSONPath\Filters\SliceFilter;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\TestCase;

class JSONPathTokenTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testBuildFilterReturnsExpectedTypes(): void
    {
        self::assertInstanceOf(
            IndexFilter::class,
            new JSONPathToken(TokenType::Index, null)->buildFilter(0)
        );
        self::assertInstanceOf(IndexesFilter::class, new JSONPathToken(TokenType::Indexes, [])->buildFilter(0));
        self::assertInstanceOf(QueryMatchFilter::class, new JSONPathToken(TokenType::QueryMatch, '')->buildFilter(0));
        self::assertInstanceOf(
            QueryResultFilter::class,
            new JSONPathToken(TokenType::QueryResult, '')->buildFilter(0)
        );
        self::assertInstanceOf(RecursiveFilter::class, new JSONPathToken(TokenType::Recursive, null)->buildFilter(0));
        self::assertInstanceOf(
            SliceFilter::class,
            new JSONPathToken(TokenType::Slice, ['start' => 0, 'end' => 0, 'step' => 1])->buildFilter(0)
        );
    }

    public function testConstructorSetsProperties(): void
    {
        $token = new JSONPathToken(TokenType::Index, 'value');

        self::assertSame(TokenType::Index, $token->type);
        self::assertSame('value', $token->value);
    }
}
