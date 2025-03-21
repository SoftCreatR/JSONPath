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
    protected const MATCH_QUERY_NEGATION_WRAPPED = '^(?<negate>!)\((?<logicalexpr>.+)\)$';

    protected const MATCH_QUERY_NEGATION_UNWRAPPED = '^(?<negate>!)(?<logicalexpr>.+)$';

    protected const MATCH_QUERY_OPERATORS = '
      (@\.(?<key>[^\s<>!=]+)|@\[["\']?(?<keySquare>.*?)["\']?\]|(?<node>@)|(%group(?<group>\d+)%))
      (\s*(?<operator>==|=~|=|<>|!==|!=|>=|<=|>|<|in|!in|nin)\s*(?<comparisonValue>.+?(?=(&&|$|\|\||%))))?
      (\s*(?<logicalandor>&&|\|\|)\s*)?
    ';

    protected const MATCH_GROUPED_EXPRESSION = '#\([^)(]*+(?:(?R)[^)(]*)*+\)#';

    /**
     * @throws JSONPathException
     */
    public function filter($collection): array
    {
        $filterExpression = $this->token->value;
        $negateFilter = false;
        if (
            \preg_match('/' . static::MATCH_QUERY_NEGATION_WRAPPED . '/x', $filterExpression, $negationMatches)
            || \preg_match('/' . static::MATCH_QUERY_NEGATION_UNWRAPPED . '/x', $filterExpression, $negationMatches)
        ) {
            $negateFilter = true;
            $filterExpression = $negationMatches['logicalexpr'];
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
                $resolve = (new JSONPath($filteredCollection))->find($filter)->getData();
                $return = $resolve;

                continue;
            }

            //Process a normal expression
            $key = $matches['key'][$expressionPart] ?: $matches['keySquare'][$expressionPart];

            $operator = $matches['operator'][$expressionPart] ?? null;
            $comparisonValue = $matches['comparisonValue'][$expressionPart] ?? null;

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
                        $foundValue = (new JSONPath($node))->find($key)->getData();

                        if ($foundValue) {
                            $selectedNode = $foundValue[0];
                            $notNothing = true;
                        }
                    }
                } else {
                    //Node selection was plain @
                    $selectedNode = $node;
                    $notNothing = true;
                }

                $comparisonResult = null;

                if ($notNothing) {
                    $comparisonResult = match ($operator) {
                        null => AccessHelper::keyExists($node, $key, $this->magicIsAllowed) || (!$key),
                        "=", "==" => $this->compareEquals($selectedNode, $comparisonValue),
                        "!=", "!==", "<>" => !$this->compareEquals($selectedNode, $comparisonValue),
                        '=~' => @\preg_match($comparisonValue, $selectedNode),
                        '<' => $this->compareLessThan($selectedNode, $comparisonValue),
                        '<=' => $this->compareLessThan($selectedNode, $comparisonValue)
                            || $this->compareEquals($selectedNode, $comparisonValue),
                        '>' => $this->compareLessThan($comparisonValue, $selectedNode), //rfc semantics
                        '>=' => $this->compareLessThan($comparisonValue, $selectedNode) //rfc semantics
                            || $this->compareEquals($selectedNode, $comparisonValue),
                        "in" => \is_array($comparisonValue) && \in_array($selectedNode, $comparisonValue, true),
                        'nin', "!in" => \is_array($comparisonValue) && !\in_array($selectedNode, $comparisonValue, true)
                    };
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

    protected function isNumber($value): bool
    {
        return !\is_string($value) && \is_numeric($value);
    }

    protected function compareEquals($a, $b): bool
    {
        $type_a = \gettype($a);
        $type_b = \gettype($b);

        if ($type_a === $type_b || ($this->isNumber($a) && $this->isNumber($b))) {
            //Primitives or Numbers
            if ($a === null || \is_scalar($a)) {
                /** @noinspection TypeUnsafeComparisonInspection */
                return $a == $b;
            }
            //Object/Array
            //@TODO array and object comparison
        }

        return false;
    }

    protected function compareLessThan($a, $b): bool
    {
        if ((\is_string($a) && \is_string($b)) || ($this->isNumber($a) && $this->isNumber($b))) {
            //numerical and string comparison supported only
            return $a < $b;
        }

        return false;
    }
}
