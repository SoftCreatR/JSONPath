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
            'no results when step negative' => [
                ['start' => 2, 'end' => 0, 'step' => -1],
                ['a', 'b', 'c'],
                [],
            ],
            'works with array object' => [
                ['start' => 0, 'end' => 2, 'step' => 1],
                new ArrayObject(['a', 'b', 'c']),
                ['a', 'b'],
            ],
        ];
    }
}
