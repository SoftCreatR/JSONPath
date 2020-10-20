<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

use Exception;
use Flow\JSONPath\JSONPath;
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
     *
     * @small
     * @dataProvider queryDataProvider
     *
     * @param string $query
     * @param string $selector
     * @param string $data
     * @param string $consensus
     * @param bool $allowFail
     * @throws Exception
     */
    public function testQueries(
        string $query,
        string $selector,
        string $data,
        string $consensus,
        bool $allowFail = false
    ): void {
        if ($allowFail) {
            try {
                self::assertEquals(
                    $consensus,
                    json_encode((new JSONPath(json_decode($data, true)))->find($selector))
                );
            } catch (ExpectationFailedException $e) {
                $comparisonFailure = $e->getComparisonFailure();

                fwrite(STDERR, "Query: {$query}\n{$comparisonFailure->toString()}");
            }
        } else {
            self::assertEquals(
                $consensus,
                json_encode((new JSONPath(json_decode($data, true)))->find($selector))
            );
        }
    }

    /**
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
            //[
            //    'Array slice with large number for start',
            //    '$[-113667776004:2]',
            //    '["first","second","third","forth","fifth"]',
            //    '[]', // Unknown consensus, might be ["first","second"],
            //    true
            //]
        ];
    }
}
