<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use JsonException;
use RuntimeException;

use const JSON_THROW_ON_ERROR;
use const PREG_OFFSET_CAPTURE;
use const PREG_UNMATCHED_AS_NULL;

class QueryMatchFilter extends AbstractFilter
{
    protected const string MATCH_QUERY_NEGATION_WRAPPED = '^(?<negate>!)\((?<logicalexpr>.+)\)$';

    protected const string MATCH_QUERY_NEGATION_UNWRAPPED = '^(?<negate>!)(?<logicalexpr>.+)$';

    protected const string MATCH_QUERY_OPERATORS = '
      (@\.(?<key>[^\s<>!=]+)|@\[["\']?(?<keySquare>.*?)["\']?\]|(?<node>@)|(%group(?<group>\d+)%))
      (\s*(?<operator>==|=~|=|<>|!==|!=|>=|<=|>|<|in|!in|nin)\s*(?<comparisonValue>.+?(?=\s*(?:&&|$|\|\||%))))?
      (\s*(?<logicalandor>&&|\|\|)\s*)?
    ';

    protected const string MATCH_GROUPED_EXPRESSION = '#\([^)(]*+(?:(?R)[^)(]*)*+\)#';

    /**
     * @throws JSONPathException
     * @inheritDoc
     */
    public function filter(array|object $collection): array
    {
        $filterExpression = $this->token->value;
        $isShorthand = $this->token->shorthand ?? false;

        if (\is_array($filterExpression)) {
            $isShorthand = $filterExpression['shorthand'] ?? $isShorthand;
            $filterExpression = $filterExpression['expression'] ?? '';
        }

        $negateFilter = false;

        if (
            \preg_match('/' . static::MATCH_QUERY_NEGATION_WRAPPED . '/x', $filterExpression, $negationMatches)
            || \preg_match('/' . static::MATCH_QUERY_NEGATION_UNWRAPPED . '/x', $filterExpression, $negationMatches)
        ) {
            $negateFilter = true;
            $filterExpression = $negationMatches['logicalexpr'];
        }

        $literalResult = $this->evaluateLiteralExpression($filterExpression, $collection);

        if ($literalResult !== null) {
            return $literalResult;
        }

        $shortCircuitResult = $this->evaluateExpressionWithTrailingLiteral($filterExpression, $collection);

        if ($shortCircuitResult !== null) {
            return $shortCircuitResult;
        }

        $filterGroups = [];

        if (
            \preg_match_all(
                static::MATCH_GROUPED_EXPRESSION,
                $filterExpression,
                $matches,
                PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL
            )
        ) {
            foreach ($matches[0] as $i => $matchesGroup) {
                $test = \substr($matchesGroup[0], 1, -1);

                //sanity check that our group is a group and not something within a string or regular expression
                if (\preg_match('/' . static::MATCH_QUERY_OPERATORS . '/x', $test)) {
                    $filterGroups[$i] = $test;
                    $filterExpression = \str_replace($matchesGroup[0], "%group{$i}%", $filterExpression);
                }
            }
        }

        $match = \preg_match_all(
            '/' . static::MATCH_QUERY_OPERATORS . '/x',
            $filterExpression,
            $matches,
            PREG_UNMATCHED_AS_NULL
        );

        if (
            $match === false
            || !isset($matches[1][0])
            || isset($matches['logicalandor'][\array_key_last($matches['logicalandor'])])
        ) {
            $constantResult = $this->evaluateConstantExpression($filterExpression);

            if ($constantResult !== null) {
                return $constantResult ? AccessHelper::arrayValues($collection) : [];
            }

            throw new RuntimeException('Malformed filter query');
        }

        $return = [];
        $matchCount = \count($matches[0]);

        for ($expressionPart = 0; $expressionPart < $matchCount; $expressionPart++) {
            $filteredCollection = $collection;
            $logicalJoin = $expressionPart > 0 ? $matches['logicalandor'][$expressionPart - 1] : null;

            if ($logicalJoin === '&&') {
                //Restrict the nodes we need to look at to those already meeting criteria
                $filteredCollection = $return;
                $return = [];
            }

            //Processing a group
            if ($matches['group'][$expressionPart] !== null) {
                $filter = '$[?(' . $filterGroups[$matches['group'][$expressionPart]] . ')]';
                $resolve = new JSONPath($filteredCollection)->find($filter)->getData();
                $return = $resolve;

                continue;
            }

            //Process a normal expression
            $key = $this->normalizeKey($matches['key'][$expressionPart] ?: $matches['keySquare'][$expressionPart]);

            $operator = $matches['operator'][$expressionPart] ?? null;
            $comparisonValue = $matches['comparisonValue'][$expressionPart] ?? null;
            $comparisonIsPath = $this->isPathComparison($comparisonValue);
            $canCompareMissing = \in_array($operator, ['=', '==', '!=', '!==', '<>'], true) && $comparisonIsPath;

            if (\is_string($comparisonValue)) {
                $comparisonValue = \preg_replace('/^\'/', '"', $comparisonValue);
                $comparisonValue = \preg_replace('/\'$/', '"', $comparisonValue);

                try {
                    $comparisonValue = \json_decode($comparisonValue, true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    //Leave $comparisonValue as raw (e.g. regular express or non quote wrapped string)
                }
            }

            foreach ($filteredCollection as $nodeIndex => $node) {
                if ($logicalJoin === '||' && \array_key_exists($nodeIndex, $return)) {
                    //Short-circuit, node already exists in output due to previous test
                    continue;
                }

                $selectedNode = null;
                $notNothing = AccessHelper::keyExists($node, $key, $this->magicIsAllowed);

                if ($key) {
                    if ($notNothing) {
                        $selectedNode = AccessHelper::getValue($node, $key, $this->magicIsAllowed);
                    } elseif (\str_contains($key, '.')) {
                        $foundValue = new JSONPath($node)->find($key)->getData();

                        if ($foundValue) {
                            $selectedNode = $foundValue[0];
                            $notNothing = true;
                        }
                    } elseif ($canCompareMissing) {
                        $notNothing = true;
                    }
                } else {
                    //Node selection was plain @
                    $selectedNode = $node;
                    $notNothing = true;
                }

                $comparisonResult = null;

                if ($notNothing) {
                    $resolvedComparisonValue = $this->resolveComparisonValue($comparisonValue, $node);
                    $comparisonResult = false;

                    switch ($operator) {
                        case null:
                            if ($key === '' || $key === null) {
                                $comparisonResult = !$isShorthand || $this->isTruthy($selectedNode);
                            } else {
                                $comparisonResult = AccessHelper::keyExists($node, $key, $this->magicIsAllowed)
                                        || (!$key);
                            }
                            break;
                        case "=":
                        case "==":
                            $comparisonResult = $this->compareEquals($selectedNode, $resolvedComparisonValue);
                            break;
                        case "!=":
                        case "!==":
                        case "<>":
                            $comparisonResult = !$this->compareEquals($selectedNode, $resolvedComparisonValue);
                            break;
                        case '=~':
                            $comparisonResult = @\preg_match(
                                (string)$resolvedComparisonValue,
                                (string)$selectedNode
                            );
                            break;
                        case '<':
                            $comparisonResult = $this->compareLessThan($selectedNode, $resolvedComparisonValue);
                            break;
                        case '<=':
                            $comparisonResult = $this->compareLessThan($selectedNode, $resolvedComparisonValue)
                                || $this->compareEquals($selectedNode, $resolvedComparisonValue);
                            break;
                        case '>':
                            //rfc semantics
                            $comparisonResult = $this->compareLessThan($resolvedComparisonValue, $selectedNode);
                            break;
                        case '>=':
                            //rfc semantics
                            $comparisonResult = $this->compareLessThan($resolvedComparisonValue, $selectedNode)
                                || $this->compareEquals($selectedNode, $resolvedComparisonValue);
                            break;
                        case "in":
                            $comparisonResult = \is_array($resolvedComparisonValue)
                                && \in_array($selectedNode, $resolvedComparisonValue, true);
                            break;
                        case 'nin':
                        case "!in":
                            $comparisonResult = \is_array($resolvedComparisonValue)
                                && !\in_array($selectedNode, $resolvedComparisonValue, true);
                            break;
                    }
                }

                if ($negateFilter) {
                    $comparisonResult = !$comparisonResult;
                }

                if ($comparisonResult) {
                    $return[$nodeIndex] = $node;
                }
            }
        }

        //Keep out returned nodes in the same order they were defined in the original collection
        \ksort($return);

        return $return;
    }

    protected function isNumber(mixed $value): bool
    {
        return !\is_string($value) && \is_numeric($value);
    }

    /**
     * @throws JSONPathException
     */
    private function resolveComparisonValue(mixed $comparisonValue, mixed $node): mixed
    {
        if (!\is_string($comparisonValue)) {
            return $comparisonValue;
        }

        if (\str_starts_with($comparisonValue, '@')) {
            $path = \substr($comparisonValue, 1);

            if ($path === '' || $path === '.') {
                return $node;
            }

            $resolved = new JSONPath($node)->find($path)->getData();

            return \is_array($resolved) && \array_key_exists(0, $resolved) ? $resolved[0] : null;
        }

        if (\str_starts_with($comparisonValue, '$')) {
            $root = $this->rootData ?? $node;
            $resolved = new JSONPath($root)->find($comparisonValue)->getData();

            return \is_array($resolved) && \array_key_exists(0, $resolved) ? $resolved[0] : null;
        }

        return $comparisonValue;
    }

    private function normalizeKey(mixed $key): int|string|null
    {
        if (\is_string($key) && \preg_match('/^-?\d+$/', $key)) {
            return (int)$key;
        }

        return $key;
    }

    private function isPathComparison(mixed $comparisonValue): bool
    {
        return \is_string($comparisonValue) && \str_starts_with($comparisonValue, '@');
    }

    private function evaluateConstantExpression(string $expression): ?bool
    {
        $pattern = '/^\s*(?<left>[^&|]+?)\s*(?<operator>==|=|!=|!==|<>|<=|>=|<|>)\s*(?<right>[^&|]+?)\s*$/';

        if (!\preg_match($pattern, $expression, $matches)) {
            return null;
        }

        $left = $this->decodeLiteral($matches['left']);
        $right = $this->decodeLiteral($matches['right']);
        $operator = $matches['operator'];

        return match ($operator) {
            '==', '=' => $this->compareEquals($left, $right),
            '!=', '!==', '<>' => !$this->compareEquals($left, $right),
            '<' => $this->compareLessThan($left, $right),
            '<=' => $this->compareLessThan($left, $right) || $this->compareEquals($left, $right),
            '>' => $this->compareLessThan($right, $left),
            '>=' => $this->compareLessThan($right, $left) || $this->compareEquals($left, $right),
        };
    }

    /**
     * @param array<int, mixed>|object $collection
     * @return array<int, mixed>|null
     * @throws JSONPathException
     */
    private function evaluateLiteralExpression(string $expression, array|object $collection): ?array
    {
        $trimmed = \trim($expression);

        if ($trimmed === '') {
            return [];
        }

        $literalValue = $this->decodeLiteral($trimmed);
        $literalIsBool = \is_bool($literalValue);

        if (!$literalIsBool && $literalValue !== null) {
            return null;
        }

        return $this->isTruthy($literalValue) ? AccessHelper::arrayValues($collection) : [];
    }

    /**
     * @param array<int, mixed>|object $collection
     * @return array<int, mixed>|null
     * @throws JSONPathException
     */
    private function evaluateExpressionWithTrailingLiteral(
        string $expression,
        array|object $collection
    ): ?array {
        if (
            !\preg_match(
                '/^(?<left>.+?)\s*(?<op>&&|\|\|)\s*(?<literal>true|false|null)\s*$/i',
                $expression,
                $matches
            )
        ) {
            return null;
        }

        $leftFilter = '$[?(' . $matches['left'] . ')]';
        $leftResult = new JSONPath($collection)->find($leftFilter)->getData();
        $literalValue = $this->decodeLiteral($matches['literal']);
        $literalIsTrue = $this->isTruthy($literalValue);

        return match ($matches['op']) {
            '&&' => $literalIsTrue ? $leftResult : [],
            '||' => $literalIsTrue ? AccessHelper::arrayValues($collection) : $leftResult,
            default => [],
        };
    }

    private function decodeLiteral(string $literal): mixed
    {
        $literal = \trim($literal);

        try {
            return \json_decode($literal, true, 512, \JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            if (\is_numeric($literal)) {
                return $literal + 0;
            }

            return $literal;
        }
    }

    private function isTruthy(mixed $value): bool
    {
        return (bool)$value;
    }

    protected function compareEquals(mixed $a, mixed $b): bool
    {
        $type_a = \gettype($a);
        $type_b = \gettype($b);

        if ($type_a === $type_b || ($this->isNumber($a) && $this->isNumber($b))) {
            //Primitives or Numbers
            if ($a === null || \is_scalar($a)) {
                /** @noinspection TypeUnsafeComparisonInspection */
                return $a == $b;
            }

            if (\is_array($a) && \is_array($b)) {
                return $this->deepEqual($a, $b);
            }

            if (\is_object($a) && \is_object($b)) {
                return $this->deepEqual((array)$a, (array)$b);
            }
        }

        return false;
    }

    /**
     * @param array<array-key, mixed> $a
     * @param array<array-key, mixed> $b
     */
    private function deepEqual(array $a, array $b): bool
    {
        $aIsList = \array_is_list($a);
        $bIsList = \array_is_list($b);

        if ($aIsList !== $bIsList) {
            return false;
        }

        if (\count($a) !== \count($b)) {
            return false;
        }

        if ($aIsList) {
            return \array_all($a, fn ($value, $index) => \array_key_exists($index, $b)
                && $this->compareEquals($value, $b[$index]));
        }

        return \array_all($a, fn ($value, $key) => \array_key_exists($key, $b)
            && $this->compareEquals($value, $b[$key]));
    }

    protected function compareLessThan(mixed $a, mixed $b): bool
    {
        if ((\is_string($a) && \is_string($b)) || ($this->isNumber($a) && $this->isNumber($b))) {
            //numerical and string comparison supported only
            return $a < $b;
        }

        return false;
    }
}
