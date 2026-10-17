<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises mode boundaries and application cases beyond the upstream fixtures.
 */
final class RFC9535Test extends TestCase
{
    /**
     * Keeps compatibility extensions and their caches independent of RFC validation.
     */
    public function testModesDoNotShareCompiledQueries(): void
    {
        $document = (object)['a-b' => 'legacy'];
        self::assertSame(['legacy'], (new JSONPath($document))->find(' $.a-b ')->getData());
        self::assertSame([], (new JSONPath($document))->find('$.0')->getData());

        $this->expectException(JSONPathException::class);
        (new JSONPath($document, JSONPath::RFC9535))->find(' $.a-b ');
    }

    /**
     * Retains RFC mode across result traversal and verifies paths without changing value types.
     */
    public function testModeIsRetainedAndPathsAreAvailableSeparately(): void
    {
        $query = new JSONPath([(object)['a' => 1], (object)['a' => 2]], JSONPath::RFC9535);
        self::assertSame([1, 2], $query->find('$[*]')->find('$[?@.a >= 1].a')->getData());
        self::assertSame(["$[0]['a']", "$[1]['a']"], $query->findPaths('$[*].a'));
        self::assertSame(["$['a']"], (new JSONPath((object)['a' => 1]))->findPaths('$.a'));
    }

    /**
     * Nested relative filters must resolve absolute paths against the enclosing document.
     */
    public function testNestedRelativeFiltersRetainTheAbsoluteRoot(): void
    {
        $document = \json_decode('{"limit":2,"rows":[{"items":[{"n":1}]},{"items":[{"n":3}]}]}', flags: \JSON_THROW_ON_ERROR);
        $query = new JSONPath($document, JSONPath::RFC9535);
        self::assertEquals([$document->rows[1]], $query->find('$.rows[?@.items[?@.n > $.limit]]')->getData());
    }

    /**
     * Object member order is irrelevant even when member names are consecutive digits.
     */
    public function testObjectEqualityAndNumericStringOrdering(): void
    {
        $document = \json_decode('[{"a":{"0":1,"1":2},"b":{"1":2,"0":1}},{"a":"10","b":"2"}]', flags: \JSON_THROW_ON_ERROR);
        $query = new JSONPath($document, JSONPath::RFC9535);
        self::assertEquals([$document[0]], $query->find('$[?@.a == @.b]')->getData());
        self::assertEquals([$document[1]], $query->find('$[?@.a < @.b]')->getData());
    }

    /**
     * Invalid character ranges produce false without emitting PHP warnings.
     */
    public function testInvalidIRegexpRanges(): void
    {
        $query = new JSONPath(['^abc$', 'abc'], JSONPath::RFC9535);
        self::assertSame([], $query->find('$[?search(@,"[z-a]")]')->getData());
    }

    /**
     * Rejects grammatically invalid queries even when no data would reach a filter.
     */
    #[DataProvider('additionalInvalidQueries')]
    public function testAdditionalGrammarErrors(string $query): void
    {
        $this->expectException(JSONPathException::class);
        (new JSONPath(null, JSONPath::RFC9535))->find($query);
    }

    /**
     * Supplies independent grammar edges, rather than duplicating upstream cases.
     *
     * @return iterable<string, array{string}>
     */
    public static function additionalInvalidQueries(): iterable
    {
        yield 'unparenthesized comparison negation' => ['$[?!@ == 1]'];
        yield 'unparenthesized double negation' => ['$[?!!@]'];
        yield 'parenthesized query is not a comparable' => ['$[?(@) == 1]'];
        yield 'parenthesized query is not NodesType' => ['$[?count((@)) == 1]'];
        yield 'vertical tab is not RFC whitespace' => ["$[?@\v==1]"];
        yield 'invalid UTF-8' => ["$.\xff"];
        yield 'unfinished descendant' => ['$..'];
        yield 'unclosed nested filter' => ['$[?@.a[?@]'];
        yield 'literal comparison group' => ['$[?(1) == 1]'];
        yield 'unfinished string escape' => ["$['unfinished\\"];
    }
}
