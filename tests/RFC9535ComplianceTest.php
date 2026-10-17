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
use stdClass;

/**
 * Runs the pinned upstream suite without weakening type, ordering, or location checks.
 */
final class RFC9535ComplianceTest extends TestCase
{
    /**
     * Checks invalid syntax independently of data, or compares complete value/path pairs.
     *
     * @throws JSONPathException
     */
    #[DataProvider('complianceCases')]
    public function testCompliance(stdClass $case): void
    {
        if ($case->invalid_selector ?? false) {
            foreach ([null, [], (object)['a' => [1, null]]] as $document) {
                try {
                    (new JSONPath($document, JSONPath::RFC9535))->find($case->selector);
                    self::fail('Invalid selector was accepted: ' . $case->selector);
                } catch (JSONPathException) {
                    self::addToAssertionCount(1);
                }
            }

            return;
        }

        $query = new JSONPath($case->document, JSONPath::RFC9535);
        $values = $query->find($case->selector)->getData();
        $paths = $query->findPaths($case->selector);
        $expectedValues = $case->results ?? [$case->result];
        $expectedPaths = $case->results_paths ?? [$case->result_paths];

        foreach ($expectedValues as $index => $expected) {
            if (self::canonicalJson($values) === self::canonicalJson($expected) && $paths === $expectedPaths[$index]) {
                self::assertCount(\count($values), $paths);

                return;
            }
        }

        self::fail('Unexpected value/path pair for ' . $case->selector . ': ' . self::canonicalJson([$values, $paths]));
    }

    /**
     * Loads the complete, unmodified suite with JSON object identity preserved.
     *
     * @return iterable<string, array{stdClass}>
     */
    public static function complianceCases(): iterable
    {
        $suite = \json_decode(\file_get_contents(__DIR__ . '/Fixtures/compliance/cts.json'), flags: \JSON_THROW_ON_ERROR);

        foreach ($suite->tests as $case) {
            yield $case->name => [$case];
        }
    }

    /**
     * Compares JSON values with unordered object members and ordered node lists.
     */
    private static function canonicalJson(mixed $value): string
    {
        return \json_encode(self::orderedObjects($value), \JSON_THROW_ON_ERROR);
    }

    /**
     * Sorts object members without converting objects to arrays or scalar types.
     */
    private static function orderedObjects(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $members = \get_object_vars($value);
            \ksort($members, \SORT_STRING);

            return (object)\array_map(self::orderedObjects(...), $members);
        }

        return \is_array($value) ? \array_map(self::orderedObjects(...), $value) : $value;
    }
}
