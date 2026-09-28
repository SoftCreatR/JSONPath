<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\Filters\Expression\ExpressionEvaluator;
use Flow\JSONPath\Filters\Expression\ExpressionLexer;
use Flow\JSONPath\Filters\Expression\ExpressionNode;
use Flow\JSONPath\Filters\Expression\ExpressionParser;
use Flow\JSONPath\Filters\Expression\ExpressionToken;
use Flow\JSONPath\Filters\Expression\ExpressionType;
use Flow\JSONPath\Filters\Expression\IRegexp;
use Flow\JSONPath\Filters\QueryMatchFilter;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathLexer;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExpressionEvaluator::class)]
#[CoversClass(ExpressionLexer::class)]
#[CoversClass(ExpressionNode::class)]
#[CoversClass(ExpressionParser::class)]
#[CoversClass(ExpressionToken::class)]
#[CoversClass(ExpressionType::class)]
#[CoversClass(IRegexp::class)]
#[CoversClass(QueryMatchFilter::class)]
#[CoversClass(JSONPathLexer::class)]
class FunctionExpressionTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testLengthCountsUnicodeScalarsAndStructuredValues(): void
    {
        $data = [
            'ab',
            'é🙂',
            'abc',
            [1, 2],
            [1, 2, 3],
            (object)['a' => 1, 'b' => 2],
            1,
            null,
        ];

        self::assertEquals(
            ['ab', 'é🙂', [1, 2], (object)['a' => 1, 'b' => 2]],
            (new JSONPath($data))->find('$[?length(@) < 3]')->getData(),
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testCountCountsNodesWithoutInspectingTheirValues(): void
    {
        $data = [
            (object)['a' => 1],
            (object)['a' => 1, 'b' => null],
            (object)[],
        ];

        self::assertEquals(
            [(object)['a' => 1]],
            (new JSONPath($data))->find('$[?count(@.*) == 1]')->getData(),
        );
        self::assertSame($data, (new JSONPath($data))->find('$[?count(@) == 1]')->getData());
    }

    /**
     * @throws JSONPathException
     */
    public function testValueReturnsOneNodeOrNothing(): void
    {
        $data = (object)[
            'c' => 'cd',
            'values' => [
                (object)['a' => 'ab'],
                (object)['c' => 'd'],
                (object)['a' => null],
            ],
        ];

        self::assertEquals(
            [(object)['c' => 'd'], (object)['a' => null]],
            (new JSONPath($data))->find('$.values[?length(@.a) == value($..c)]')->getData(),
        );
        self::assertSame(
            $data->values,
            (new JSONPath($data))->find('$.values[?value(@.missing) == length(@.missing)]')->getData(),
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testMatchAndSearchUseFullAndSubstringSemantics(): void
    {
        $data = [
            (object)['value' => 'Robert'],
            (object)['value' => 'Bobby'],
            (object)['value' => 'Alice'],
            (object)['value' => 42],
        ];

        self::assertEquals(
            [(object)['value' => 'Robert']],
            (new JSONPath($data))->find('$[?match(@.value, "[BR]obert")]')->getData(),
        );
        self::assertEquals(
            [(object)['value' => 'Robert'], (object)['value' => 'Bobby']],
            (new JSONPath($data))->find('$[?search(@.value, "[BR]ob")]')->getData(),
        );
        self::assertEquals(
            [(object)['value' => 'Alice'], (object)['value' => 42]],
            (new JSONPath($data))->find('$[?!search(@.value, "[BR]ob")]')->getData(),
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testFunctionsComposeWithRootQueriesAndLogicalExpressions(): void
    {
        $data = (object)[
            'minimum' => 2,
            'values' => [
                (object)['name' => 'ab', 'children' => [1, 2]],
                (object)['name' => 'abcd', 'children' => [1]],
            ],
        ];

        self::assertEquals(
            [(object)['name' => 'ab', 'children' => [1, 2]]],
            (new JSONPath($data))
                ->find('$.values[?length(@ .name) == $ .minimum && count(@.children[*]) == 2]')
                ->getData(),
        );
        self::assertSame(
            $data->values,
            (new JSONPath($data))
                ->find('$.values[?(length("🙂") == 1 || (match("x", "y") && count(@.*) > 0))]')
                ->getData(),
        );
        self::assertSame(
            $data->values,
            (new JSONPath($data))->find('$.values[? length(@.name) >= 2]')->getData(),
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testFunctionComparisonsUseRfcValueSemantics(): void
    {
        $data = [
            (object)['left' => [1], 'right' => [1]],
            (object)['left' => [1], 'right' => [1, 2]],
            (object)['left' => (object)['a' => 1], 'right' => (object)['a' => 1]],
            (object)['left' => (object)['a' => 1], 'right' => (object)['b' => 1]],
            (object)['left' => 1, 'right' => '1'],
            (object)['left' => 'same', 'right' => 'same'],
        ];

        self::assertEquals(
            [$data[0], $data[2], $data[5]],
            (new JSONPath($data))->find('$[?value(@.left) == value(@.right)]')->getData(),
        );
        self::assertEquals(
            [$data[1], $data[3], $data[4]],
            (new JSONPath($data))->find('$[?value(@.left) != value(@.right)]')->getData(),
        );
        self::assertSame([1, 2, 3], (new JSONPath([1, 2, 3]))->find('$[?length("a") <= @]')->getData());
        self::assertSame([2, 3], (new JSONPath([1, 2, 3]))->find('$[?@ > length("a")]')->getData());
        self::assertSame([1, 2, 3], (new JSONPath([1, 2, 3]))->find('$[?@ >= length("a")]')->getData());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidExpressionProvider(): iterable
    {
        yield 'non-singular length argument' => ['$[?length(@.*) < 3]'];
        yield 'literal count argument' => ['$[?count(1) == 1]'];
        yield 'logical result in comparison' => ['$[?match(@.timezone, "Europe/.*") == true]'];
        yield 'value result used as test' => ['$[?value(@..color)]'];
        yield 'missing argument' => ['$[?count()]'];
        yield 'too many arguments' => ['$[?length(@, @)]'];
        yield 'unknown function' => ['$[?unknown(@)]'];
        yield 'value literal used as test' => ['$[?length("abc")]'];
        yield 'unterminated string' => ['$[?match(@, "abc)]'];
        yield 'invalid number' => ['$[?length(@) == 01]'];
        yield 'unexpected character' => ['$[?length(@) = 1]'];
        yield 'unclosed function' => ['$[?length(@]'];
        yield 'non-singular union' => ['$[?length(@["a", "b"]) == 1]'];
        yield 'missing expression' => ['$[?length(,) == 1]'];
        yield 'empty dot member' => ['$[?length(@.) == 1]'];
        yield 'invalid query continuation' => ['$[?length(@foo) == 1]'];
        yield 'unclosed query bracket' => ['$[?length(@[) == 1]'];
    }

    /**
     * @throws JSONPathException
     */
    #[DataProvider('invalidExpressionProvider')]
    public function testIllTypedAndMalformedFunctionsAreRejected(string $query): void
    {
        $this->expectException(JSONPathException::class);

        (new JSONPath([]))->find($query);
    }

    public function testIRegexpValidationAndTranslation(): void
    {
        self::assertTrue(IRegexp::matches('abc', '^ab.*', true));
        self::assertTrue(IRegexp::matches('zabz', 'ab', false));
        self::assertFalse(IRegexp::matches("a\nb", 'a.b', false));
        self::assertTrue(IRegexp::matches('é', '\\p{L}', true));
        self::assertTrue(IRegexp::matches('é', '[\\p{L}]', true));
        self::assertTrue(IRegexp::matches('-', '[-]', true));
        self::assertTrue(IRegexp::matches('-', '[a-]', true));
        self::assertTrue(IRegexp::matches('a', '[a-z]{1,2}', true));
        self::assertTrue(IRegexp::matches('aa', '(a|b){2}', true));
        self::assertTrue(IRegexp::matches('aaa', 'a{2,}', true));
        self::assertTrue(IRegexp::matches('~', '~', true));
        self::assertTrue(IRegexp::matches("\n", '\\n', true));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidIRegexpProvider(): iterable
    {
        yield 'invalid UTF-8' => ["\xFF"];
        yield 'dangling escape' => ['\\'];
        yield 'unsupported escape' => ['\\d'];
        yield 'lookahead' => ['(?=a)'];
        yield 'empty class' => ['[]'];
        yield 'empty negated class' => ['[^]'];
        yield 'unclosed class' => ['[a'];
        yield 'invalid class character' => ['[[]'];
        yield 'invalid property' => ['\\p{BasicLatin}'];
        yield 'invalid class range endpoint' => ['[a--]'];
        yield 'unclosed group' => ['(a'];
        yield 'stray closing group' => ['a)'];
        yield 'stray quantifier' => ['*a'];
        yield 'unclosed range quantifier' => ['a{1'];
        yield 'empty range quantifier' => ['a{}'];
        yield 'reversed range quantifier' => ['a{2,1}'];
        yield 'excessive range quantifier' => ['a{1,10001}'];
    }

    #[DataProvider('invalidIRegexpProvider')]
    public function testInvalidIRegexpsReturnFalse(string $expression): void
    {
        self::assertFalse(IRegexp::matches('anything', $expression, true));
    }

    /**
     * @throws JSONPathException
     */
    public function testLexerDecodesSingleQuotedLiteralsAndNumbers(): void
    {
        $tokens = (new ExpressionLexer("match('a\\'b', 'a.*') || length(-1.5e2) == 3"))->tokenize();

        self::assertSame('match', $tokens[0]->value);
        self::assertSame("a'b", $tokens[2]->value);
        self::assertSame(-150.0, $tokens[9]->value);
        self::assertSame(ExpressionToken::END, $tokens[13]->type);
    }

    /**
     * @throws JSONPathException
     */
    public function testLexerDecodesAllLiteralForms(): void
    {
        $tokens = (new ExpressionLexer("'a\\\"b' 'line\\n' true false null"))->tokenize();

        self::assertSame('a"b', $tokens[0]->value);
        self::assertSame("line\n", $tokens[1]->value);
        self::assertTrue($tokens[2]->value);
        self::assertFalse($tokens[3]->value);
        self::assertNull($tokens[4]->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidLexerProvider(): iterable
    {
        yield 'invalid escape' => ["'\\q'"];
        yield 'overflowing number' => ['1e999'];
    }

    #[DataProvider('invalidLexerProvider')]
    public function testLexerRejectsInvalidLiterals(string $expression): void
    {
        $this->expectException(JSONPathException::class);

        (new ExpressionLexer($expression))->tokenize();
    }

    /**
     * @throws JSONPathException
     */
    public function testStandaloneQueryExpressionUsesNodeExistence(): void
    {
        $expression = (new ExpressionParser('@.present'))->parse();
        $evaluator = new ExpressionEvaluator([]);

        self::assertTrue($evaluator->matches($expression, (object)['present' => null]));
        self::assertFalse($evaluator->matches($expression, (object)[]));
    }

    /**
     * @throws JSONPathException
     */
    public function testParserClassifiesBracketQueries(): void
    {
        $expression = (new ExpressionParser('length(@[\'a\']) == 1'))->parse();

        self::assertSame(ExpressionNode::COMPARISON, $expression->kind);

        $this->expectException(JSONPathException::class);

        (new ExpressionParser('length(@[)'))->parse();
    }

    /**
     * @throws JSONPathException
     */
    public function testEvaluatorRejectsInvalidManuallyConstructedNode(): void
    {
        $this->expectException(JSONPathException::class);

        (new ExpressionEvaluator([]))->matches(
            new ExpressionNode(ExpressionNode::LITERAL, ExpressionType::Logical, true),
            null,
        );
    }

    public function testEvaluatorDefensiveErrors(): void
    {
        $literal = new ExpressionNode(ExpressionNode::LITERAL, ExpressionType::Value, 1);
        $invalidExpressions = [
            new ExpressionNode(ExpressionNode::LITERAL, ExpressionType::Nodes, 1),
            new ExpressionNode(
                ExpressionNode::COMPARISON,
                ExpressionType::Logical,
                'invalid',
                [$literal, $literal],
            ),
            new ExpressionNode(ExpressionNode::FUNCTION, ExpressionType::Logical, 'invalid'),
            new ExpressionNode(
                ExpressionNode::COMPARISON,
                ExpressionType::Logical,
                '==',
                [new ExpressionNode(ExpressionNode::LOGICAL_NOT, ExpressionType::Value), $literal],
            ),
        ];

        foreach ($invalidExpressions as $expression) {
            try {
                (new ExpressionEvaluator([]))->matches($expression, null);
                self::fail('Invalid expression was accepted');
            } catch (JSONPathException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
