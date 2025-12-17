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
use PHPUnit\Framework\TestCase;

class JSONPathLexerTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testIndexWildcard(): void
    {
        $tokens = new JSONPathLexer('.*')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals("*", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexSimple(): void
    {
        $tokens = new JSONPathLexer('.foo')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals("foo", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexRecursive(): void
    {
        $tokens = new JSONPathLexer('..teams.*')
            ->parseExpression();

        self::assertCount(3, $tokens);
        self::assertEquals(TokenType::Recursive, $tokens[0]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals(TokenType::Index, $tokens[1]->type);
        self::assertEquals('teams', $tokens[1]->value);
        self::assertEquals(TokenType::Index, $tokens[2]->type);
        self::assertEquals('*', $tokens[2]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexComplex(): void
    {
        $tokens = new JSONPathLexer('["\'b.^*_"]')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals("'b.^*_", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexBadlyFormed(): void
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token hello* in expression: .hello*');

        new JSONPathLexer('.hello*')
            ->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexInteger(): void
    {
        $tokens = new JSONPathLexer('[0]')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertSame(0, $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexIntegerAfterDotNotation(): void
    {
        $tokens = new JSONPathLexer('.books[0]')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals(TokenType::Index, $tokens[1]->type);
        self::assertEquals("books", $tokens[0]->value);
        self::assertSame(0, $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexWord(): void
    {
        $tokens = new JSONPathLexer('["foo$-/\'"]')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals("foo$-/'", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexWordWithWhitespace(): void
    {
        $tokens = new JSONPathLexer('[   "foo$-/\'"     ]')
            ->parseExpression();

        self::assertEquals(TokenType::Index, $tokens[0]->type);
        self::assertEquals("foo$-/'", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testSliceSimple(): void
    {
        $tokens = new JSONPathLexer('[0:1:2]')
            ->parseExpression();

        self::assertEquals(TokenType::Slice, $tokens[0]->type);
        self::assertEquals(['start' => 0, 'end' => 1, 'step' => 2], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexNegativeIndex(): void
    {
        $tokens = new JSONPathLexer('[-1]')
            ->parseExpression();

        self::assertEquals(TokenType::Slice, $tokens[0]->type);
        self::assertEquals(['start' => -1, 'end' => null, 'step' => null], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testSliceAllNull(): void
    {
        $tokens = new JSONPathLexer('[:]')
            ->parseExpression();

        self::assertEquals(TokenType::Slice, $tokens[0]->type);
        self::assertEquals(['start' => null, 'end' => null, 'step' => null], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testQueryResultSimple(): void
    {
        $tokens = new JSONPathLexer('[(@.foo + 2)]')
            ->parseExpression();

        self::assertEquals(TokenType::QueryResult, $tokens[0]->type);
        self::assertEquals('@.foo + 2', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testQueryMatchSimple(): void
    {
        $tokens = new JSONPathLexer('[?(@.foo < \'bar\')]')
            ->parseExpression();

        self::assertEquals(TokenType::QueryMatch, $tokens[0]->type);
        self::assertEquals('@.foo < \'bar\'', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testQueryMatchNotEqualTO(): void
    {
        $tokens = new JSONPathLexer('[?(@.foo != \'bar\')]')
            ->parseExpression();

        self::assertEquals(TokenType::QueryMatch, $tokens[0]->type);
        self::assertEquals('@.foo != \'bar\'', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testQueryMatchBrackets(): void
    {
        $tokens = new JSONPathLexer("[?(@['@language']='en')]")
            ->parseExpression();

        self::assertEquals(TokenType::QueryMatch, $tokens[0]->type);
        self::assertEquals("@['@language']='en'", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testRecursiveSimple(): void
    {
        $tokens = new JSONPathLexer('..foo')
            ->parseExpression();

        self::assertEquals(TokenType::Recursive, $tokens[0]->type);
        self::assertEquals(TokenType::Index, $tokens[1]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals('foo', $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testRecursiveWildcard(): void
    {
        $tokens = new JSONPathLexer('..*')
            ->parseExpression();

        self::assertEquals(TokenType::Recursive, $tokens[0]->type);
        self::assertEquals(TokenType::Index, $tokens[1]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals('*', $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testRecursiveBadlyFormed(): void
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token ba^r in expression: ..ba^r');

        new JSONPathLexer('..ba^r')
            ->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexesSimple(): void
    {
        $tokens = new JSONPathLexer('[1,2,3]')
            ->parseExpression();

        self::assertEquals(TokenType::Indexes, $tokens[0]->type);
        self::assertEquals([1, 2, 3], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testIndexesWhitespace(): void
    {
        $tokens = new JSONPathLexer('[ 1,2 , 3]')
            ->parseExpression();

        self::assertEquals(TokenType::Indexes, $tokens[0]->type);
        self::assertEquals([1, 2, 3], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testEmptyExpressionsReturnNoTokens(): void
    {
        self::assertSame([], new JSONPathLexer('')->parseExpression());
        self::assertSame([], new JSONPathLexer('$')->parseExpression());
    }

    /**
     * @throws JSONPathException
     */
    public function testSingleCharacterExpressionNormalized(): void
    {
        self::assertSame([], new JSONPathLexer('.')->parseExpression());
    }
}
