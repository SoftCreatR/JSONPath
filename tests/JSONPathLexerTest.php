<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath\Test;

use PHPUnit\Framework\TestCase;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathLexer;
use Flow\JSONPath\JSONPathToken;

class JSONPathLexerTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function test_Index_Wildcard()
    {
        $tokens = (new JSONPathLexer('.*'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("*", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_Simple()
    {
        $tokens = (new JSONPathLexer('.foo'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("foo", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_Recursive()
    {
        $tokens = (new JSONPathLexer('..teams.*'))->parseExpression();

        self::assertCount(3, $tokens);
        self::assertEquals(JSONPathToken::T_RECURSIVE, $tokens[0]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals(JSONPathToken::T_INDEX, $tokens[1]->type);
        self::assertEquals('teams', $tokens[1]->value);
        self::assertEquals(JSONPathToken::T_INDEX, $tokens[2]->type);
        self::assertEquals('*', $tokens[2]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_Complex()
    {
        $tokens = (new JSONPathLexer('["\'b.^*_"]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("'b.^*_", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_BadlyFormed()
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token hello* in expression: .hello*');

        (new JSONPathLexer('.hello*'))->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_Integer()
    {
        $tokens = (new JSONPathLexer('[0]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("0", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_IntegerAfterDotNotation()
    {
        $tokens = (new JSONPathLexer('.books[0]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals(JSONPathToken::T_INDEX, $tokens[1]->type);
        self::assertEquals("books", $tokens[0]->value);
        self::assertEquals("0", $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_Word()
    {
        $tokens = (new JSONPathLexer('["foo$-/\'"]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("foo$-/'", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_WordWithWhitespace()
    {
        $tokens = (new JSONPathLexer('[   "foo$-/\'"     ]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEX, $tokens[0]->type);
        self::assertEquals("foo$-/'", $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Slice_Simple()
    {
        $tokens = (new JSONPathLexer('[0:1:2]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_SLICE, $tokens[0]->type);
        self::assertEquals(['start' => 0, 'end' => 1, 'step' => 2], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Index_NegativeIndex()
    {
        $tokens = (new JSONPathLexer('[-1]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_SLICE, $tokens[0]->type);
        self::assertEquals(['start' => -1, 'end' => null, 'step' => null], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Slice_AllNull()
    {
        $tokens = (new JSONPathLexer('[:]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_SLICE, $tokens[0]->type);
        self::assertEquals(['start' => null, 'end' => null, 'step' => null], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_QueryResult_Simple()
    {
        $tokens = (new JSONPathLexer('[(@.foo + 2)]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_QUERY_RESULT, $tokens[0]->type);
        self::assertEquals('@.foo + 2', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_QueryMatch_Simple()
    {
        $tokens = (new JSONPathLexer('[?(@.foo < \'bar\')]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_QUERY_MATCH, $tokens[0]->type);
        self::assertEquals('@.foo < \'bar\'', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_QueryMatch_NotEqualTO()
    {
        $tokens = (new JSONPathLexer('[?(@.foo != \'bar\')]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_QUERY_MATCH, $tokens[0]->type);
        self::assertEquals('@.foo != \'bar\'', $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_QueryMatch_Brackets()
    {
        $tokens = (new JSONPathLexer("[?(@['@language']='en')]"))->parseExpression();

        self::assertEquals(JSONPathToken::T_QUERY_MATCH, $tokens[0]->type);
        self::assertEquals("@['@language']='en'", $tokens[0]->value);

    }

    /**
     * @throws JSONPathException
     */
    public function test_Recursive_Simple()
    {
        $tokens = (new JSONPathLexer('..foo'))->parseExpression();

        self::assertEquals(JSONPathToken::T_RECURSIVE, $tokens[0]->type);
        self::assertEquals(JSONPathToken::T_INDEX, $tokens[1]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals('foo', $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Recursive_Wildcard()
    {
        $tokens = (new JSONPathLexer('..*'))->parseExpression();

        self::assertEquals(JSONPathToken::T_RECURSIVE, $tokens[0]->type);
        self::assertEquals(JSONPathToken::T_INDEX, $tokens[1]->type);
        self::assertEquals(null, $tokens[0]->value);
        self::assertEquals('*', $tokens[1]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Recursive_BadlyFormed()
    {
        $this->expectException(JSONPathException::class);
        $this->expectExceptionMessage('Unable to parse token ba^r in expression: ..ba^r');

        (new JSONPathLexer('..ba^r'))->parseExpression();
    }

    /**
     * @throws JSONPathException
     */
    public function test_Indexes_Simple()
    {
        $tokens = (new JSONPathLexer('[1,2,3]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEXES, $tokens[0]->type);
        self::assertEquals([1, 2, 3], $tokens[0]->value);
    }

    /**
     * @throws JSONPathException
     */
    public function test_Indexes_Whitespace()
    {
        $tokens = (new JSONPathLexer('[ 1,2 , 3]'))->parseExpression();

        self::assertEquals(JSONPathToken::T_INDEXES, $tokens[0]->type);
        self::assertEquals([1, 2, 3], $tokens[0]->value);
    }
}
