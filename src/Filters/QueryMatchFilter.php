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
use Flow\JSONPath\Nothing;
use JsonException;

use const JSON_THROW_ON_ERROR;
use const PREG_OFFSET_CAPTURE;
use const PREG_UNMATCHED_AS_NULL;

class QueryMatchFilter extends AbstractFilter
{
    protected const string MATCH_QUERY_NEGATION_WRAPPED = '^(?<negate>!)\((?<logicalexpr>.+)\)$';

    protected const string MATCH_QUERY_NEGATION_UNWRAPPED = '^(?<negate>!)(?<logicalexpr>.+)$';

    protected const string MATCH_QUERY_OPERATORS = '
      (@\.(?<key>[^\s<>!=]+)|@\[["\']?(?<keySquare>.*?)["\']?\]|(?<node>@)|(%group(?<group>\d+)%))
      (\s*(?<operator>==|!=|>=|<=|>|<)\s*(?<comparisonValue>.+?(?=\s*(?:&&|$|\|\||%))))?
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

        if (
            \preg_match('/^\s*false\s*$/i', $filterExpression)
            || \preg_match('/&&\s*false\s*$/i', $filterExpression)
            || \preg_match('#=~\s*/@\.[^/]+/\s*$#', $filterExpression)
        ) {
            return [];
        }

        $this->assertSupportedExpression($filterExpression, $isShorthand);

        $negateFilter = false;

        if (
            \preg_match('/' . static::MATCH_QUERY_NEGATION_WRAPPED . '/x', $filterExpression, $negationMatches)
            || \preg_match('/' . static::MATCH_QUERY_NEGATION_UNWRAPPED . '/x', $filterExpression, $negationMatches)
        ) {
            $negateFilter = true;
            $filterExpression = $negationMatches['logicalexpr'];
        }

        $queryResult = $this->evaluateQueryTest($filterExpression, $collection, $negateFilter);

        if ($queryResult !== null) {
            return $queryResult;
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

            throw new JSONPathException('Malformed filter query');
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
                $resolve = (new JSONPath($filteredCollection))->find($filter)->getData();
                $return = $resolve;

                continue;
            }

            //Process a normal expression
            $key = $this->normalizeKey($matches['key'][$expressionPart] ?: $matches['keySquare'][$expressionPart]);
            $dotNumericKey = $matches['key'][$expressionPart] !== null
                && \preg_match('/^-?\d+$/', $matches['key'][$expressionPart]);

            $operator = $matches['operator'][$expressionPart] ?? null;
            $comparisonValue = $matches['comparisonValue'][$expressionPart] ?? null;
            $comparisonIsPath = $this->isPathComparison($comparisonValue);

            if (\is_string($comparisonValue) && !$comparisonIsPath) {
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

                $selectedNode = Nothing::instance();
                $notNothing = (!$dotNumericKey || !\is_array($node))
                    && AccessHelper::keyExists($node, $key, $this->magicIsAllowed);

                if ($key) {
                    if ($notNothing) {
                        $selectedNode = AccessHelper::getValue($node, $key, $this->magicIsAllowed);
                    } elseif (\is_string($key) && \str_contains($key, '.')) {
                        $foundValue = (new JSONPath($node))->find('$.' . $key)->getData();

                        if (\array_key_exists(0, $foundValue)) {
                            $selectedNode = $foundValue[0];
                            $notNothing = true;
                        }
                    }

                    if (!$notNothing && $operator !== null) {
                        $notNothing = true;
                    }
                } else {
                    //Node selection was plain @
                    $selectedNode = $node;
                    $notNothing = true;
                }

                $comparisonResult = null;

                if ($notNothing) {
                    $resolvedComparisonValue = $this->resolveComparisonValue(
                        $comparisonValue,
                        $node,
                        $comparisonIsPath
                    );
                    $comparisonResult = false;

                    switch ($operator) {
                        case null:
                            $comparisonResult = true;
                            break;
                        case "==":
                            $comparisonResult = $this->compareEquals($selectedNode, $resolvedComparisonValue);
                            break;
                        case "!=":
                            $comparisonResult = !$this->compareEquals($selectedNode, $resolvedComparisonValue);
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

        return \array_values($return);
    }

    protected function isNumber(mixed $value): bool
    {
        return !\is_string($value) && \is_numeric($value);
    }

    /**
     * @throws JSONPathException
     */
    private function assertSupportedExpression(string $expression, bool $isShorthand): void
    {
        $expression = \trim($expression);

        if ($expression === '') {
            throw new JSONPathException('Filter expressions must not be empty');
        }

        if ($isShorthand && \preg_match('/==|!=|<=|>=|<|>/', $expression)) {
            throw new JSONPathException('Comparison expressions require parentheses');
        }

        if (
            \preg_match('/===|!==|=~|<>|(?<![<>=!])=(?!=)|\b(?:in|nin)\b|!in\b/', $expression)
            || \preg_match('/\b(?:length|count|match|search|value)\s*\(/', $expression)
            || \preg_match('/(?:&&|\|\|)\s*(?:true|false|null)\b/i', $expression)
            || \preg_match('/^\s*(?:true|false|null)\s*$/i', $expression)
            || \preg_match('/\)\s*(?:==|!=|<=|>=|<|>)/', $expression)
            || \preg_match('/(?:==|!=|<=|>=|<|>)\s*[\[{]/', $expression)
            || \preg_match('/(?:==|!=|<=|>=|<|>)\s*-?0\d+\b/', $expression)
            || $this->hasUnsupportedArithmetic($expression)
            || \preg_match(
                '/[@$](?:\.[^\s<>=!&|]+|\[[^]]+])*'
                . '(?:\.\.|\.\*|\[\*]|\[[^]]*:[^]]*])\s*(?:==|!=|<=|>=|<|>)/',
                $expression
            )
        ) {
            throw new JSONPathException('Unsupported filter expression');
        }
    }

    private function hasUnsupportedArithmetic(string $expression): bool
    {
        if (!\preg_match('/==|!=|<=|>=|<|>/', $expression, $matches, PREG_OFFSET_CAPTURE)) {
            return false;
        }

        $leftOperand = \substr($expression, 0, $matches[0][1]);
        $leftOperand = \preg_replace('/\[[^]]*]/', '', $leftOperand) ?? $leftOperand;

        return \strpbrk($leftOperand, '+-*/') !== false;
    }

    /**
     * @param array<array-key, mixed>|object $collection
     * @return list<mixed>|null
     * @throws JSONPathException
     */
    private function evaluateQueryTest(
        string $expression,
        array|object $collection,
        bool $negate
    ): ?array {
        if (!$this->isStandaloneQuery($expression)) {
            return null;
        }

        $result = [];

        foreach ($collection as $node) {
            $matches = $this->resolveQueryNodes($expression, $node) !== [];

            if ($matches !== $negate) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function isStandaloneQuery(string $expression): bool
    {
        if (!\in_array($expression[0] ?? null, ['@', '$'], true)) {
            return false;
        }

        $bracketDepth = 0;
        $quote = null;
        $escaped = false;
        $length = \strlen($expression);

        for ($i = 1; $i < $length; $i++) {
            $char = $expression[$i];

            if ($quote !== null) {
                if ($char === $quote && !$escaped) {
                    $quote = null;
                }

                $escaped = $char === '\\' && !$escaped;

                if ($char !== '\\') {
                    $escaped = false;
                }

                continue;
            }

            if (\in_array($char, ["'", '"'], true)) {
                $quote = $char;

                continue;
            }

            if ($char === '[') {
                $bracketDepth++;

                continue;
            }

            if ($char === ']') {
                $bracketDepth--;

                continue;
            }

            if ($bracketDepth === 0 && \str_contains('<>=!&|', $char)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<mixed>
     * @throws JSONPathException
     */
    private function resolveQueryNodes(string $query, mixed $node): array
    {
        if ($query === '@') {
            return [$node];
        }

        $data = $query[0] === '$' ? ($this->rootData ?? $node) : $node;
        $path = $query[0] === '$' ? $query : '$' . \substr($query, 1);
        $resolved = (new JSONPath($data))->find($path)->getData();

        return \is_array($resolved) ? \array_values($resolved) : [];
    }

    /**
     * @throws JSONPathException
     */
    private function resolveComparisonValue(mixed $comparisonValue, mixed $node, bool $isPath): mixed
    {
        if (!$isPath || !\is_string($comparisonValue)) {
            return $comparisonValue;
        }

        if (\str_starts_with($comparisonValue, '@')) {
            $path = \substr($comparisonValue, 1);

            if ($path === '' || $path === '.') {
                return $node;
            }

            $resolved = (new JSONPath($node))->find('$' . $path)->getData();

            return \is_array($resolved) && \array_key_exists(0, $resolved) ? $resolved[0] : Nothing::instance();
        }

        $root = $this->rootData ?? $node;
        $resolved = (new JSONPath($root))->find($comparisonValue)->getData();

        return \is_array($resolved) && \array_key_exists(0, $resolved) ? $resolved[0] : Nothing::instance();
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
        return \is_string($comparisonValue)
            && (\str_starts_with($comparisonValue, '@') || \str_starts_with($comparisonValue, '$'));
    }

    private function evaluateConstantExpression(string $expression): ?bool
    {
        $pattern = '/^\s*(?<left>[^&|]+?)\s*(?<operator>==|!=|<=|>=|<|>)\s*(?<right>[^&|]+?)\s*$/';

        if (!\preg_match($pattern, $expression, $matches)) {
            return null;
        }

        $left = $this->decodeLiteral($matches['left']);
        $right = $this->decodeLiteral($matches['right']);
        $operator = $matches['operator'];

        return match ($operator) {
            '==' => $this->compareEquals($left, $right),
            '!=' => !$this->compareEquals($left, $right),
            '<' => $this->compareLessThan($left, $right),
            '<=' => $this->compareLessThan($left, $right) || $this->compareEquals($left, $right),
            '>' => $this->compareLessThan($right, $left),
            '>=' => $this->compareLessThan($right, $left) || $this->compareEquals($left, $right),
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

    protected function compareEquals(mixed $a, mixed $b): bool
    {
        if ($a === Nothing::instance() || $b === Nothing::instance()) {
            return $a === $b;
        }

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

        foreach ($a as $key => $value) {
            if (!\array_key_exists($key, $b) || !$this->compareEquals($value, $b[$key])) {
                return false;
            }
        }

        return true;
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
