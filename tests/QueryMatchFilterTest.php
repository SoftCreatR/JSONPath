<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\QueryMatchFilter;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(QueryMatchFilter::class)]
class QueryMatchFilterTest extends TestCase
{
    /**
     * @return iterable<string, array{data: mixed, expression: string, expected: array<int, mixed>}>
     */
    public static function filterProvider(): iterable
    {
        yield 'shorthand truthy filters values' => [
            'data' => [0, 1, '', 'value', false],
            'expression' => '$[?@]',
            'expected' => [1 => 1, 3 => 'value'],
        ];

        yield 'negation wrapped' => [
            'data' => [['flag' => true], ['flag' => false]],
            'expression' => '$[?(!(@.flag==true))]',
            'expected' => [['flag' => false]],
        ];

        yield 'negation unwrapped' => [
            'data' => [['flag' => true], ['flag' => false]],
            'expression' => '$[?(!@.flag==true)]',
            'expected' => [['flag' => false]],
        ];

        yield 'grouped logical expressions' => [
            'data' => [
                ['active' => true, 'score' => 1],
                ['active' => true, 'score' => 2],
                ['active' => false, 'score' => 3],
            ],
            'expression' => '$[?(@.active==true && (@.score>1))]',
            'expected' => [['active' => true, 'score' => 2]],
        ];

        yield 'path comparison current and root' => [
            'data' => [
                'threshold' => 5,
                'items' => [
                    ['v' => 5, 'w' => 5],
                    ['v' => 4, 'w' => 5],
                ],
            ],
            'expression' => '$.items[?(@.v==@.w && @.v==$.threshold)]',
            'expected' => [['v' => 5, 'w' => 5]],
        ];

        yield 'missing key compared to path still evaluates' => [
            'data' => [['foo' => 1], ['foo' => 1, 'bar' => 1]],
            'expression' => '$[?(@.bar==@.foo)]',
            'expected' => [['foo' => 1, 'bar' => 1]],
        ];

        yield 'dot separated key resolves through jsonpath' => [
            'data' => [
                ['nested' => ['value' => 3]],
                ['nested' => ['value' => 4]],
            ],
            'expression' => '$[?(@.nested.value==3)]',
            'expected' => [['nested' => ['value' => 3]]],
        ];

        yield 'deep equal lists and objects' => [
            'data' => [
                ['left' => [1, 2], 'right' => [1, 2]],
                ['left' => [1, 2], 'right' => [2, 1]],
                ['left' => (object)['a' => 1, 'b' => 2], 'right' => (object)['b' => 2, 'a' => 1]],
                ['left' => (object)['a' => 1], 'right' => (object)['a' => 2]],
            ],
            'expression' => '$[?(@.left==@.right)]',
            'expected' => [
                ['left' => [1, 2], 'right' => [1, 2]],
                ['left' => (object)['a' => 1, 'b' => 2], 'right' => (object)['b' => 2, 'a' => 1]],
            ],
        ];

        yield 'plain node selection compares current node' => [
            'data' => [0, 1, 2],
            'expression' => '$[?(@==@)]',
            'expected' => [0, 1, 2],
        ];

        yield 'deep equal failure branches' => [
            'data' => [
                ['left' => [1, 2], 'right' => ['a' => 1, 'b' => 2]],
                ['left' => [1], 'right' => [1, 2]],
            ],
            'expression' => '$[?(@.left==@.right)]',
            'expected' => [],
        ];

        yield 'regex comparison' => [
            'data' => ['foo', 'bar'],
            'expression' => '$[?(@ =~ /fo.*/)]',
            'expected' => ['foo'],
        ];

        yield 'in operator' => [
            'data' => [1, 2, 3],
            'expression' => '$[?(@ in [1,3])]',
            'expected' => [1, 3],
        ];

        yield 'nin operator with short circuit or' => [
            'data' => [
                ['a' => 1],
                ['b' => 1],
                ['a' => 3],
            ],
            'expression' => '$[?(@.a nin [2,3] || @.b==1)]',
            'expected' => [
                ['a' => 1],
                ['b' => 1],
            ],
        ];

        yield 'existence check without operator' => [
            'data' => [
                ['value' => 1],
                ['other' => 2],
            ],
            'expression' => '$[?(@.value)]',
            'expected' => [
                ['value' => 1],
            ],
        ];

        yield '!in operator' => [
            'data' => [1, 2, 3],
            'expression' => '$[?(@ !in [2])]',
            'expected' => [1, 3],
        ];

        yield 'less than comparison' => [
            'data' => [['n' => 1], ['n' => 3]],
            'expression' => '$[?(@.n<2)]',
            'expected' => [['n' => 1]],
        ];

        yield 'less or equal comparison' => [
            'data' => [['n' => 1], ['n' => 2], ['n' => 3]],
            'expression' => '$[?(@.n<=2)]',
            'expected' => [['n' => 1], ['n' => 2]],
        ];

        yield 'greater or equal comparison' => [
            'data' => [['n' => 1], ['n' => 2], ['n' => 3]],
            'expression' => '$[?(@.n>=2)]',
            'expected' => [['n' => 2], ['n' => 3]],
        ];

        yield 'not equals comparison' => [
            'data' => [['value' => 1], ['value' => 2]],
            'expression' => '$[?(@.value!=2)]',
            'expected' => [['value' => 1]],
        ];
    }

    /**
     * @param array<int, mixed> $expected
     * @throws JSONPathException
     */
    #[DataProvider('filterProvider')]
    public function testFilterScenarios(mixed $data, string $expression, array $expected): void
    {
        $result = new JSONPath($data)->find($expression)->getData();

        self::assertEquals(\array_values($expected), \array_values($result));
    }

    /**
     * @return iterable<string, array{expression: string, expectMatch: bool}>
     */
    public static function constantExpressionProvider(): iterable
    {
        yield 'num comparison true' => ['expression' => '[?(1<2)]', 'expectMatch' => true];
        yield 'num comparison false' => ['expression' => '[?(2>3)]', 'expectMatch' => false];
        yield 'num with leading zeros decoded as number' => ['expression' => '[?(0123==123)]', 'expectMatch' => true];
        yield 'string literal decoding' => ['expression' => '[?(foo==foo)]', 'expectMatch' => true];
        yield 'invalid less than comparison for non-scalars' => ['expression' => '[?([]<1)]', 'expectMatch' => false];
        yield 'not equals' => ['expression' => '[?(2!=3)]', 'expectMatch' => true];
        yield 'less or equal' => ['expression' => '[?(2<=2)]', 'expectMatch' => true];
        yield 'greater or equal' => ['expression' => '[?(1>=2)]', 'expectMatch' => false];
        yield 'json literal deep equal' => ['expression' => '[?({"a":1}=={"a":1})]', 'expectMatch' => true];
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('constantExpressionProvider')]
    public function testConstantExpressions(string $expression, bool $expectMatch): void
    {
        $data = ['keep'];
        $result = new JSONPath($data)->find('$' . $expression)->getData();

        self::assertSame($expectMatch ? ['keep'] : [], $result);
    }

    /**
     * @throws JSONPathException
     */
    public function testShorthandTokenValueArrayFiltersTruthyNodes(): void
    {
        $token = new JSONPathToken(TokenType::QueryMatch, ['expression' => '@', 'shorthand' => true]);
        $filter = new QueryMatchFilter($token);

        $collection = [0, 1, '', 'value', false];

        self::assertSame([1 => 1, 3 => 'value'], $filter->filter($collection));
    }

    /**
     * @throws JSONPathException
     */
    public function testMalformedFilterThrowsRuntimeException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed filter query');

        new JSONPath([1])->find('$[?(foo)]');
    }

    /**
     * @throws JSONPathException
     */
    public function testLiteralOnlyFilterExpressionsReturnWholeCollectionOrEmpty(): void
    {
        $data = [1, 2, 3];

        self::assertSame($data, new JSONPath($data)->find('$[?(true)]')->getData());
        self::assertSame([], new JSONPath($data)->find('$[?(false)]')->getData());
    }

    /**
     * @throws JSONPathException
     */
    public function testLogicalExpressionsWithLiteralRightOperand(): void
    {
        $data = [
            ['key' => 1],
            ['key' => -1],
        ];

        self::assertSame(
            [],
            new JSONPath($data)->find('$[?(@.key>0 && false)]')->getData()
        );
        self::assertSame(
            $data,
            new JSONPath($data)->find('$[?(@.key>0 || true)]')->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testEmptyFilterExpressionReturnsEmpty(): void
    {
        self::assertSame([], new JSONPath([1, 2])->find('$[?()]')->getData());
    }

    /**
     * @throws JSONPathException
     */
    public function testNormalizeKeyCastsNumericStrings(): void
    {
        $token = new JSONPathToken(TokenType::QueryMatch, '@["2"]=="two"');
        $filter = new QueryMatchFilter($token);

        $result = $filter->filter([
            ['2' => 'two', '1' => 'one'],
            ['2' => 'nope', '1' => 'one'],
        ]);

        self::assertSame([['2' => 'two', '1' => 'one']], \array_values($result));
    }
}
