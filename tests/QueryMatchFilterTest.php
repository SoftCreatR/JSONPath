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
            'expected' => [0, 1, '', 'value', false],
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
        $result = (new JSONPath($data))->find($expression)->getData();

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
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('constantExpressionProvider')]
    public function testConstantExpressions(string $expression, bool $expectMatch): void
    {
        $data = ['keep'];
        $result = (new JSONPath($data))->find('$' . $expression)->getData();

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

        self::assertSame($collection, $filter->filter($collection));
    }

    /**
     * @throws JSONPathException
     */
    public function testMalformedFilterThrowsJSONPathException(): void
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Malformed filter query');

        (new JSONPath([1]))->find('$[?(foo)]');
    }

    /**
     * @throws JSONPathException
     */
    public function testDirectEmptyFilterTokenIsRejected(): void
    {
        $this->expectException(JSONPathException::class);

        (new QueryMatchFilter(new JSONPathToken(TokenType::QueryMatch, '')))->filter([1]);
    }

    /**
     * @throws JSONPathException
     */
    public function testFalseFilterExpressionReturnsEmpty(): void
    {
        $data = [1, 2, 3];

        self::assertSame([], (new JSONPath($data))->find('$[?(false)]')->getData());
    }

    /**
     * @throws JSONPathException
     */
    public function testLogicalAndFalseReturnsEmpty(): void
    {
        $data = [
            ['key' => 1],
            ['key' => -1],
        ];

        self::assertSame(
            [],
            (new JSONPath($data))->find('$[?(@.key>0 && false)]')->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testEmptyFilterExpressionIsRejected(): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPath([1, 2]))->find('$[?()]');
    }

    /**
     * @throws JSONPathException
     */
    public function testComparisonRegressionCases(): void
    {
        self::assertSame(
            [['key' => 1]],
            (new JSONPath(['key' => 42, 'another' => ['key' => 1]]))
                ->find('$[?(@.key)]')
                ->getData()
        );
        self::assertSame(
            [[], ['left' => null, 'right' => null]],
            (new JSONPath([[], ['left' => null], ['right' => null], ['left' => null, 'right' => null]]))
                ->find('$[?(@.left==@.right)]')
                ->getData()
        );
        self::assertEquals(
            [['a' => [1]], ['a' => (object)['x' => 'y']]],
            (new JSONPath([['a' => []], ['a' => [1]], ['a' => (object)['x' => 'y']]]))
                ->find('$[?(@.a.*)]')
                ->getData()
        );
        self::assertSame(
            [['a' => [['price' => 11]]]],
            (new JSONPath([['a' => [['price' => 1]]], ['a' => [['price' => 11]]]]))
                ->find('$[?(@.a[?(@.price>10)])]')
                ->getData()
        );
        self::assertSame(
            [['nodes' => [['child' => 1]]]],
            (new JSONPath([['nodes' => [['child' => 1]]], ['nodes' => [[]]]]))
                ->find('$[?(@..child)]')
                ->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testNumericSelectorsInFiltersRespectNotation(): void
    {
        self::assertSame(
            [[0, 2], [2]],
            (new JSONPath([[2, 3], ['a'], [0, 2], [2]]))->find('$[?(@[-1]==2)]')->getData()
        );
        self::assertSame(
            [],
            (new JSONPath([['first', 'second', 'third']]))->find("$[?(@.2 == 'third')]")->getData()
        );
        self::assertEquals(
            [(object)['2' => 'second']],
            (new JSONPath([(object)['2' => 'second']]))->find("$[?(@.2 == 'second')]")->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testMissingMemberIsNotEqualToScalar(): void
    {
        self::assertSame(
            [['key' => 1], ['other' => 1]],
            (new JSONPath([['key' => 1], ['key' => 42], ['other' => 1]]))
                ->find('$[?(@.key!=42)]')
                ->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testMemberBasedRegularExpressionRemainsAnEmptyResult(): void
    {
        self::assertSame(
            [],
            (new JSONPath([['name' => 'hello', 'pattern' => 'hello']]))
                ->find('$[?(@.name=~/@.pattern/)]')
                ->getData()
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testUnquotedStringComparisonAndOrShortCircuit(): void
    {
        self::assertSame(
            [['value' => 'word']],
            (new JSONPath([['value' => 'word'], ['value' => 'other']]))
                ->find('$[?(@.value==word)]')
                ->getData()
        );
        self::assertSame(
            [['a' => 1, 'b' => 1], ['a' => 0, 'b' => 1]],
            (new JSONPath([['a' => 1, 'b' => 1], ['a' => 0, 'b' => 1]]))
                ->find('$[?(@.a==1 || @.b==1)]')
                ->getData()
        );
        self::assertSame(
            [['a' => false, 'b' => false], ['a' => true, 'c' => true]],
            (new JSONPath([['a' => false, 'b' => false], ['a' => true, 'c' => true], ['c' => true]]))
                ->find('$[?(@.a && (@.b || @.c))]')
                ->getData()
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedFilterProvider(): iterable
    {
        yield 'single equals' => ['$[?(@.key=42)]'];
        yield 'triple equals' => ['$[?(@.key===42)]'];
        yield 'strict not equals' => ['$[?(@.key!==42)]'];
        yield 'alternate not equals' => ['$[?(@.key<>42)]'];
        yield 'regular expression' => ['$[?(@.name=~/hello.*/)]'];
        yield 'membership' => ['$[?(@.key in [1,2])]'];
        yield 'negated membership' => ['$[?(@.key !in [1,2])]'];
        yield 'function extension' => ['$[?(length(@)==1)]'];
        yield 'literal true test' => ['$[?(true)]'];
        yield 'literal null test' => ['$[?(null)]'];
        yield 'and true' => ['$[?(@.key>0 && true)]'];
        yield 'or false' => ['$[?(@.key>0 || false)]'];
        yield 'array comparison' => ['$[?(@.key==[1,2])]'];
        yield 'object comparison' => ['$[?(@.key=={"a":1})]'];
        yield 'leading zero number' => ['$[?(@.key==010)]'];
        yield 'arithmetic' => ['$[?(@.key+1==2)]'];
        yield 'dashed filter shorthand' => ["$[?(@.key-dash=='value')]"];
        yield 'non-singular comparison' => ['$[?(@.*==1)]'];
        yield 'logical result comparison' => ['$[?((@.key<2)==false)]'];
        yield 'comparison without wrapper' => ['$[?@.key==42]'];
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('unsupportedFilterProvider')]
    public function testRejectsUnsupportedFilterExpressions(string $expression): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPath([['key' => 1]]))->find($expression);
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
