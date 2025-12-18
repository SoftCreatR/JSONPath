<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use ArrayObject;
use Flow\JSONPath\Filters\IndexFilter;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(IndexFilter::class)]
class IndexFilterTest extends TestCase
{
    /**
     * @throws JSONPathException
     */
    public function testArrayValueTokenReturnsOnlyExistingKeys(): void
    {
        $token = new JSONPathToken(TokenType::Index, [0, 2, 99]);
        $filter = new IndexFilter($token);

        self::assertSame(
            ['first', 'third'],
            $filter->filter(['first', 'second', 'third'])
        );
    }

    /**
     * @throws JSONPathException
     */
    public function testSingleIndexWorksForObjectsAndArrayAccess(): void
    {
        $token = new JSONPathToken(TokenType::Index, 'prop');
        $filter = new IndexFilter($token);
        $object = (object)['prop' => 5];

        self::assertSame([5], $filter->filter($object));

        $arrayObject = new ArrayObject(['prop' => 'value']);
        self::assertSame(['value'], $filter->filter($arrayObject));
    }

    /**
     * @throws JSONPathException
     */
    public function testWildcardReturnsValuesAndLengthReturnsCount(): void
    {
        $wildcard = new IndexFilter(new JSONPathToken(TokenType::Index, '*'));
        $length = new IndexFilter(new JSONPathToken(TokenType::Index, 'length'));

        $input = ['a' => 1, 'b' => 2];

        self::assertSame([1, 2], $wildcard->filter($input));
        self::assertSame([2], $length->filter($input));
    }

    /**
     * @throws JSONPathException
     */
    public function testReturnsEmptyWhenKeyMissing(): void
    {
        $filter = new IndexFilter(new JSONPathToken(TokenType::Index, 'missing'));

        self::assertSame([], $filter->filter(['present' => 1]));
    }

    /**
     * @throws JSONPathException
     */
    public function testJSONPathFindOnScalarProducesEmptyCollection(): void
    {
        $result = new JSONPath(123)->find('$.missing');

        self::assertSame([], $result->getData());
    }
}
