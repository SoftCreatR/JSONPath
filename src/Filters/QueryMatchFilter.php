<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use RuntimeException;

class QueryMatchFilter extends AbstractFilter
{
    protected const MATCH_QUERY_OPERATORS = '
      @(\.(?<key>[^\s<>!=]+)|\[["\']?(?<keySquare>.*?)["\']?\])
      (\s*(?<operator>==|=~|=|<>|!==|!=|>=|<=|>|<|in|!in|nin)\s*(?<comparisonValue>.+))?
    ';

    public function filter($collection): array
    {
        \preg_match('/^' . static::MATCH_QUERY_OPERATORS . '$/x', $this->token->value, $matches);

        if (!isset($matches[1])) {
            throw new RuntimeException('Malformed filter query');
        }

        $key = $matches['key'] ?: $matches['keySquare'];

        if ($key === '') {
            throw new RuntimeException('Malformed filter query: key was not set');
        }

        $operator = $matches['operator'] ?? null;
        $comparisonValue = $matches['comparisonValue'] ?? null;

        if (\is_string($comparisonValue)) {
            if (\str_starts_with($comparisonValue, "[") && \str_ends_with($comparisonValue, "]")) {
                $comparisonValue = \substr($comparisonValue, 1, -1);
                $comparisonValue = \preg_replace('/^[\'"]/', '', $comparisonValue);
                $comparisonValue = \preg_replace('/[\'"]$/', '', $comparisonValue);
                $comparisonValue = \preg_replace('/[\'"], *[\'"]/', ',', $comparisonValue);
                $comparisonValue = \array_map('trim', \explode(",", $comparisonValue));
            } else {
                $comparisonValue = \preg_replace('/^[\'"]/', '', $comparisonValue);
                $comparisonValue = \preg_replace('/[\'"]$/', '', $comparisonValue);

                if (\strtolower($comparisonValue) === 'false') {
                    $comparisonValue = false;
                } elseif (\strtolower($comparisonValue) === 'true') {
                    $comparisonValue = true;
                } elseif (\strtolower($comparisonValue) === 'null') {
                    $comparisonValue = null;
                }
            }
        }

        $return = [];

        foreach ($collection as $value) {
            if (AccessHelper::keyExists($value, $key, $this->magicIsAllowed)) {
                $value1 = AccessHelper::getValue($value, $key, $this->magicIsAllowed);

                if ($operator === null && $value1) {
                    $return[] = $value;
                }

                /** @noinspection TypeUnsafeComparisonInspection */
                // phpcs:ignore -- This is a loose comparison by design.
                if (($operator === '=' || $operator === '==') && $value1 == $comparisonValue) {
                    $return[] = $value;
                }

                /** @noinspection TypeUnsafeComparisonInspection */
                // phpcs:ignore -- This is a loose comparison by design.
                if (($operator === '!=' || $operator === '!==' || $operator === '<>') && $value1 != $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '=~' && @\preg_match($comparisonValue, $value1)) {
                    $return[] = $value;
                }

                if ($operator === '>' && $value1 > $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '>=' && $value1 >= $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '<' && $value1 < $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '<=' && $value1 <= $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === 'in' && \is_array($comparisonValue) && \in_array($value1, $comparisonValue, false)) {
                    $return[] = $value;
                }

                if (
                    ($operator === 'nin' || $operator === '!in')
                    && \is_array($comparisonValue)
                    && !\in_array($value1, $comparisonValue, false)
                ) {
                    $return[] = $value;
                }
            }
        }

        return $return;
    }
}
