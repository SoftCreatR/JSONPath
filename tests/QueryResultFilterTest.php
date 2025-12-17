<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\QueryResultFilter;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(QueryResultFilter::class)]
class QueryResultFilterTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testFilterResolvesComputedIndex(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.foo + 2');
        $filter = new QueryResultFilter($token);

        $collection = [
            'foo' => 3,
            5 => 'bar',
        ];

        self::assertSame(['bar'], $filter->filter($collection));
    }

    /**
     * @throws JSONPathException
     */
    public function testFilterReturnsEmptyWhenLengthExceeded(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.length + 1');
        $filter = new QueryResultFilter($token);

        $collection = ['a', 'b'];

        self::assertSame([], $filter->filter($collection));
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('arithmeticProvider')]
    public function testFilterSupportsAllArithmeticOperators(string $expression, int|float $expectedIndex): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, $expression);
        $filter = new QueryResultFilter($token);

        $collection = [
            'value' => 4,
            $expectedIndex => 'found',
        ];

        self::assertSame(['found'], $filter->filter($collection));
    }

    /**
     * @return list<array{string, int|float}>
     */
    public static function arithmeticProvider(): array
    {
        return [
            ['@.value - 1', 3],
            ['@.value * 2', 8],
            ['@.value / 2', 2],
        ];
    }

    /**
     * @throws JSONPathException
     */
    public function testFilterFallsBackToLengthWhenKeyMissing(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.length - 1');
        $filter = new QueryResultFilter($token);

        $collection = ['zero', 'one'];

        self::assertSame(['one'], $filter->filter($collection));
    }

    /**
     * @throws JSONPathException
     */
    public function testFilterReturnsEmptyWhenKeyNotFound(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.missing + 1');
        $filter = new QueryResultFilter($token);

        self::assertSame([], $filter->filter(['foo' => 'bar']));
    }

    /**
     * @throws JSONPathException
     */
    public function testFilterReturnsEmptyWhenComputedIndexMissing(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.foo + 10');
        $filter = new QueryResultFilter($token);

        self::assertSame([], $filter->filter(['foo' => 1]));
    }

    /**
     * @throws JSONPathException
     */
    public function testFilterReturnsEmptyWhenResultKeyMissing(): void
    {
        $token = new JSONPathToken(TokenType::QueryResult, '@.foo + 100');
        $filter = new QueryResultFilter($token);

        self::assertSame([], $filter->filter(['foo' => 1]));
    }

    public function testUnsupportedOperatorThrows(): void
    {
        $this->expectException(JSONPathException::class);

        $token = new JSONPathToken(TokenType::QueryResult, '@.foo ^ 2');
        new QueryResultFilter($token)->filter(['foo' => 1]);
    }
}
