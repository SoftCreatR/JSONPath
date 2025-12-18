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
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\TestCase;

class JSONPathCoreTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testFindAndHelpers(): void
    {
        $data = [
            'list' => [
                ['v' => 1],
                ['v' => 2],
                ['v' => 3],
            ],
            'nested' => ['inner' => ['x' => 9]],
        ];

        $path = new JSONPath($data);
        $slice = $path->find('$.list[1:3]');

        self::assertSame([['v' => 2], ['v' => 3]], $slice->getData());

        $first = $path->first();
        $last = $path->last();

        self::assertSame($data['list'], $first instanceof JSONPath ? $first->getData() : $first);
        self::assertSame(['inner' => ['x' => 9]], $last instanceof JSONPath ? $last->getData() : $last);
        self::assertSame('list', $path->firstKey());
        self::assertSame('nested', $path->lastKey());
    }

    public function testOffsetAccessAndIteration(): void
    {
        $path = new JSONPath(['child' => ['a' => 1]]);

        self::assertTrue($path->offsetExists('child'));

        /** @var JSONPath $child */
        $child = $path['child'];

        self::assertInstanceOf(JSONPath::class, $child);
        self::assertSame(['a' => 1], $child->getData());

        $path[] = 'appended';
        $path['new'] = 'value';
        unset($path['child']);

        $collected = [];

        foreach ($path as $key => $value) {
            $collected[$key] = $value instanceof JSONPath ? $value->getData() : $value;
        }

        self::assertArrayHasKey(0, $collected);
        self::assertSame('appended', $collected[0]);
        self::assertSame('value', $collected['new']);
        self::assertEquals(new JSONPathException('oops'), new JSONPathException('oops'));
        self::assertSame('index', TokenType::Index->value);
    }

    /**
     * @throws JSONPathException
     */
    public function testParseTokensCachesResults(): void
    {
        $path = new JSONPath(['a' => ['b' => 1]]);
        $first = $path->parseTokens('$.a.b');
        $second = $path->parseTokens('$.a.b');

        self::assertNotEmpty($first);
        self::assertSame($first, $second);
    }

    public function testJsonSerializeAndMagicGet(): void
    {
        $path = new JSONPath(['a' => 1]);

        self::assertSame(['a' => 1], $path->jsonSerialize());
        self::assertSame(1, $path->__get('a'));
        self::assertNull($path->__get('missing'));

        $empty = new JSONPath([]);

        self::assertNull($empty->first());
        self::assertNull($empty->last());
        self::assertNull($empty->firstKey());
        self::assertNull($empty->lastKey());
        self::assertSame(0, $empty->count());
    }

    /**
     * @throws JSONPathException
     */
    public function testFindOnScalarReturnsEmptyResult(): void
    {
        $result = new JSONPath(123)->find('$.missing')->getData();

        self::assertSame([], $result);
    }
}
