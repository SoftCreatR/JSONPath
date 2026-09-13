<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathLexer;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(JSONPathLexer::class)]
class JSONPathLexerTest extends TestCase
{
    /**
     * @param list<array{type: TokenType, value: mixed, shorthand?: bool}> $expectedTokens
     * @throws JSONPathException
     */
    #[DataProvider('expressionProvider')]
    public function testParsesExpressions(string $expression, array $expectedTokens): void
    {
        $tokens = (new JSONPathLexer($expression))->parseExpression();

        self::assertCount(\count($expectedTokens), $tokens);

        foreach ($expectedTokens as $i => $expected) {
            self::assertEquals($expected['type'], $tokens[$i]->type);
            self::assertEquals($expected['value'], $tokens[$i]->value);

            if (\array_key_exists('shorthand', $expected)) {
                self::assertSame($expected['shorthand'], $tokens[$i]->shorthand);
            }
        }
    }

    /**
     * @return iterable<string, array{string, list<array{type: TokenType, value: mixed, shorthand?: bool}>}>
     */
    public static function expressionProvider(): iterable
    {
        yield 'wildcard index' => [
            '$.*',
            [
                ['type' => TokenType::Index, 'value' => '*'],
            ],
        ];

        yield 'simple index' => [
            '$.foo',
            [
                ['type' => TokenType::Index, 'value' => 'foo'],
            ],
        ];

        yield 'bare index normalizes dot prefix' => [
            'foo',
            [
                ['type' => TokenType::Index, 'value' => 'foo'],
            ],
        ];

        yield 'complex quoted index' => [
            '$["\'b.^*_"]',
            [
                ['type' => TokenType::Index, 'value' => "'b.^*_"],
            ],
        ];

        yield 'integer index' => [
            '$[0]',
            [
                ['type' => TokenType::Index, 'value' => 0],
            ],
        ];

        yield 'index after dot notation' => [
            '$.books[0]',
            [
                ['type' => TokenType::Index, 'value' => 'books'],
                ['type' => TokenType::Index, 'value' => 0],
            ],
        ];

        yield 'quoted index with whitespace' => [
            '$[   "foo$-/\'"     ]',
            [
                ['type' => TokenType::Index, 'value' => "foo$-/'"],
            ],
        ];

        yield 'slice with explicit bounds' => [
            '$[0:1:2]',
            [
                ['type' => TokenType::Slice, 'value' => ['start' => 0, 'end' => 1, 'step' => 2]],
            ],
        ];

        yield 'negative index' => [
            '$[-1]',
            [
                ['type' => TokenType::Index, 'value' => -1],
            ],
        ];

        yield 'slice all nulls' => [
            '$[:]',
            [
                ['type' => TokenType::Slice, 'value' => ['start' => null, 'end' => null, 'step' => null]],
            ],
        ];

        yield 'shorthand query current' => [
            '$[?@]',
            [
                ['type' => TokenType::QueryMatch, 'value' => '@', 'shorthand' => true],
            ],
        ];

        yield 'double quoted index with escape' => [
            '$["a\\"b"]',
            [
                ['type' => TokenType::Index, 'value' => 'a"b'],
            ],
        ];

        yield 'union with slice and negative index' => [
            '$[-2,1:3]',
            [
                [
                    'type' => TokenType::Indexes,
                    'value' => [
                        -2,
                        [
                            'type' => 'slice',
                            'value' => ['start' => 1, 'end' => 3, 'step' => null],
                        ],
                    ],
                ],
            ],
        ];

        yield 'single quoted index with escapes' => [
            "$['back\\\\slash\\'quote']",
            [
                ['type' => TokenType::Index, 'value' => "back\\slash'quote"],
            ],
        ];

        yield 'multiple quoted indexes collapse to array' => [
            '$["first","second"]',
            [
                ['type' => TokenType::Index, 'value' => ['first', 'second'], 'quoted' => true],
            ],
        ];

        yield 'empty quoted index resolves to empty string' => [
            '$[""]',
            [
                ['type' => TokenType::Index, 'value' => '', 'quoted' => true],
            ],
        ];

        yield 'quoted index preserves brackets and dollar signs' => [
            '$[\'[$the.size$]\']',
            [
                ['type' => TokenType::Index, 'value' => '[$the.size$]', 'quoted' => true],
            ],
        ];

        yield 'query match' => [
            "$[?(@['@language']='en')]",
            [
                ['type' => TokenType::QueryMatch, 'value' => "@['@language']='en'"],
            ],
        ];

        yield 'recursive simple' => [
            '$..foo',
            [
                ['type' => TokenType::Recursive, 'value' => null],
                ['type' => TokenType::Index, 'value' => 'foo'],
            ],
        ];

        yield 'recursive wildcard' => [
            '$..*',
            [
                ['type' => TokenType::Recursive, 'value' => null],
                ['type' => TokenType::Index, 'value' => '*'],
            ],
        ];

        yield 'indexes with whitespace' => [
            '$[ 1,2 , 3]',
            [
                ['type' => TokenType::Indexes, 'value' => [1, 2, 3]],
            ],
        ];
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexBadlyFormed(): void
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token hello* in expression: .hello*');

        (new JSONPathLexer('$.hello*'))->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function testRecursiveBadlyFormed(): void
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token ba^r in expression: ..ba^r');

        (new JSONPathLexer('$..ba^r'))->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function testRootExpressionReturnsNoTokens(): void
    {
        self::assertSame([], (new JSONPathLexer('$'))->parseExpression());
    }

    /**
     * @throws JSONPathException
     */
    public function testEmptyExpressionIsRejected(): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPathLexer(''))->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function testUnclosedBracketThrowsAfterFinalFlush(): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPathLexer("$['unterminated"))->parseExpression();
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupportedExpressionProvider(): iterable
    {
        yield 'current identifier at query root' => ['@.a'];
        yield 'leading dot without root' => ['.key'];
        yield 'root without segment operator' => ['$a'];
        yield 'empty bracket selector' => ['$[]'];
        yield 'unquoted bracket member' => ['$[key]'];
        yield 'dot before bracket selector' => ['$.["key"]'];
        yield 'quoted dot member' => ['$.' . "'key'"];
        yield 'quote inside member shorthand' => ['$.key"suffix'];
        yield 'triple-dot descent' => ['$...key'];
        yield 'trailing dot' => ['$.'];
        yield 'incomplete descendant segment' => ['$..'];
        yield 'quoted descendant shorthand' => ['$..' . "'key'"];
        yield 'root identifier as member shorthand' => ['$.$'];
        yield 'whitespace-padded member shorthand' => ['$. key'];
        yield 'two adjacent quoted selectors' => ["$['two'.'some']"];
        yield 'mixed wildcard union' => ['$[*,1]'];
        yield 'filter union' => ['$[1,?(@.foo>1)]'];
        yield 'script expression' => ['$[(@.length-1)]'];
        yield 'empty shorthand filter' => ['$[?]'];
        yield 'empty parenthesized filter' => ['$[?()]'];
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('unsupportedExpressionProvider')]
    public function testRejectsUnsupportedExpressions(string $expression): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPathLexer($expression))->parseExpression();
    }
}
