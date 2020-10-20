<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Flow\JSONPath\{JSONPath, JSONPathException};
use PHPUnit\Framework\{ExpectationFailedException, TestCase};

use function fwrite;
use function json_decode;
use function json_encode;

use const STDERR;

class QueryTest extends TestCase
{
    /**
     * This method aims to test the current implementation against
     * all queries listed on https://cburgmer.github.io/json-path-comparison/
     *
     * Every test performed is basically disallowed to fail, but can be set to
     * allowed, if for example a specific query isn't supported, yet.
     *
     * If a test is allowed to fail and fails, the assertion result will be
     * printed to the console (using STDERR), so we know, what's going on.
     *
     * @see https://cburgmer.github.io/json-path-comparison
     * @dataProvider queryDataProvider
     * @param string $query
     * @param string $selector
     * @param string $data
     * @param string $consensus
     * @param bool $allowFail
     * @param bool $skip
     * @throws JSONPathException
     */
    public function testQueries(
        string $query,
        string $selector,
        string $data,
        string $consensus,
        bool $allowFail = false,
        bool $skip = false
    ): void {
        if ($skip) {
            // Avoid "This test did not perform any assertions"
            // but do not use markTestSkipped, to prevent unnecessary
            // console outputs
            self::assertTrue(true);

            return;
        }

        $results = json_encode((new JSONPath(json_decode($data, true)))->find($selector));

        if ($allowFail) {
            try {
                self::assertEquals($consensus, $results);
            } catch (ExpectationFailedException $e) {
                $comparisonFailure = $e->getComparisonFailure();

                fwrite(STDERR, "Query: {$query}\n{$comparisonFailure->toString()}");
            }
        } else {
            self::assertEquals($consensus, $results);
        }
    }

    /**
     * Returns a list of queries, test data and expected results.
     *
     * A hand full of queries may run forever, thus they should
     * be skipped.
     *
     * Queries that are currently known as "problematic" are:
     *
     * - array_slice_with_negative_step_and_start_greater_than_end
     * - array_slice_with_open_end_and_negative_step
     * - array_slice_with_large_number_for_start
     * - array_slice_with_large_number_for_end
     * - array_slice_with_open_start_and_negative_step
     * - array_slice_with_negative_step_only
     *
     * @return string[]
     * @todo Finish this list
     */
    public function queryDataProvider(): array
    {
        return [
            [
                'Array Slice',
                '$[1:3]',
                '["first","second","third","forth","fifth"]',
                '["second","third"]',
            ],
            [
                'Array slice on exact match',
                '$[0:5]',
                '["first","second","third","forth","fifth"]',
                '["first","second","third","forth","fifth"]',
            ],
            [
                'Array slice on non overlapping array',
                '$[7:10]',
                '["first","second","third"]',
                '[]',
            ],
            [
                'Array slice on object',
                '$[1:3]',
                '{":":42,"more":"string","a":1,"b":2,"c":3,"1:3":"nice"}',
                '[]',
            ],
            [
                'Array slice on partially overlapping array',
                '$[1:10]',
                '["first","second","third"]',
                '["second","third"]',
            ],
            [
                'Array slice with large number for end and negative step',
                '$[2:-113667776004:-1]',
                '["first", "second", "third", "forth", "fifth"]',
                '[]', // Unknown consensus, might be ["third","second","first"]
            ],
            [
                'Array slice with large number for start',
                '$[-113667776004:2]',
                '["first","second","third","forth","fifth"]',
                '[]', // Unknown consensus, might be ["first","second"],
                true, // Allow fail
                true // Skip
            ]
        ];
    }
}
