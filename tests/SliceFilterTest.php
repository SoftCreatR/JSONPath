<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use ArrayObject;
use Flow\JSONPath\Filters\SliceFilter;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(SliceFilter::class)]
class SliceFilterTest extends TestCase
{
    /**
     * @param array<string, int|null> $slice
     * @param array<array-key, mixed>|object $input
     * @param array<int, mixed> $expected
     */
    #[DataProvider('sliceProvider')]
    public function testFilterHandlesNegativeAndNullBounds(array $slice, array|object $input, array $expected): void
    {
        $token = new JSONPathToken(TokenType::Slice, $slice);
        $filter = new SliceFilter($token);

        self::assertSame($expected, $filter->filter($input));
    }

    /**
     * @return iterable<string, array{array<string, int|null>, array<array-key, mixed>, array<int, mixed>}>
     */
    public static function edgeCaseProvider(): iterable
    {
        yield 'step zero returns empty' => [
            ['start' => 0, 'end' => null, 'step' => 0],
            ['a', 'b'],
            [],
        ];

        yield 'start beyond length yields empty' => [
            ['start' => 5, 'end' => null, 'step' => 1],
            ['a', 'b'],
            [],
        ];

        yield 'end beyond length clamps to length' => [
            ['start' => 0, 'end' => 10, 'step' => 1],
            ['a', 'b'],
            ['a', 'b'],
        ];

        yield 'negative step with null bounds reverses' => [
            ['start' => null, 'end' => null, 'step' => -1],
            ['a', 'b', 'c'],
            ['c', 'b', 'a'],
        ];

        yield 'positive step with end below zero yields empty' => [
            ['start' => 0, 'end' => -10, 'step' => 1],
            ['a', 'b', 'c'],
            [],
        ];

        yield 'negative step with start far below length clamps to -1' => [
            ['start' => -5, 'end' => null, 'step' => -1],
            ['a', 'b', 'c'],
            [],
        ];

        yield 'negative step with start beyond length clamps to last index' => [
            ['start' => 10, 'end' => null, 'step' => -1],
            ['a', 'b', 'c'],
            ['c', 'b', 'a'],
        ];

        yield 'negative step with end beyond length clamps end' => [
            ['start' => 1, 'end' => 10, 'step' => -1],
            ['a', 'b', 'c'],
            [],
        ];
        yield 'negative step with very negative start clamps to -1 and high end clamps to length' => [
            ['start' => -5, 'end' => 10, 'step' => -1],
            ['a', 'b', 'c'],
            [],
        ];
        yield 'negative step with end far below zero still collects prefix' => [
            ['start' => 1, 'end' => -10, 'step' => -1],
            ['a', 'b', 'c'],
            ['b', 'a'],
        ];
    }

    /**
     * @param array<string, int|null> $slice
     * @param array<array-key, mixed> $input
     * @param array<int, mixed> $expected
     */
    #[DataProvider('edgeCaseProvider')]
    public function testEdgeCases(array $slice, array $input, array $expected): void
    {
        $token = new JSONPathToken(TokenType::Slice, $slice);
        $filter = new SliceFilter($token);

        self::assertSame($expected, $filter->filter($input));
    }

    /**
     * @return array<string, array{array<string, int|null>, array<array-key, mixed>|object, array<int, mixed>}>
     */
    public static function sliceProvider(): array
    {
        return [
            'negative start clamps at zero' => [
                ['start' => -10, 'end' => 2, 'step' => 1],
                ['a', 'b', 'c'],
                ['a', 'b'],
            ],
            'negative end wraps from length' => [
                ['start' => 0, 'end' => -1, 'step' => 1],
                ['a', 'b', 'c'],
                ['a', 'b'],
            ],
            'nulls default to full length' => [
                ['start' => null, 'end' => null, 'step' => 2],
                ['a', 'b', 'c', 'd'],
                ['a', 'c'],
            ],
            'negative step slices in reverse order' => [
                ['start' => 2, 'end' => 0, 'step' => -1],
                ['a', 'b', 'c'],
                ['c', 'b'],
            ],
            'works with array object' => [
                ['start' => 0, 'end' => 2, 'step' => 1],
                new ArrayObject(['a', 'b', 'c']),
                ['a', 'b'],
            ],
        ];
    }
}
