<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use RuntimeException;

class QueryMatchFilter extends AbstractFilter
{
    protected const MATCH_QUERY_OPERATORS = '
      @(\.(?<key>[^\s<>!=]+)|\[["\']?(?<keySquare>.*?)["\']?\])
      (\s*(?<operator>==|=~|=|<>|!==|!=|>=|<=|>|<|in|!in|nin)\s*(?<comparisonValue>.+))?
    ';

    /**
     * @throws JSONPathException
     */
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
            $value1 = null;

            if (AccessHelper::keyExists($value, $key, $this->magicIsAllowed)) {
                $value1 = AccessHelper::getValue($value, $key, $this->magicIsAllowed);
            } elseif (\str_contains($key, '.')) {
                $value1 = (new JSONPath($value))->find($key)->getData()[0] ?? '';
            }

            if ($value1) {
                if ($operator === null) {
                    $return[] = $value;
                }

                $comparisonResult = match ($operator) {
                    null => null,
                    "=","==" => $value1 == $comparisonValue,
                    "!=","!==","<>" => $value1 != $comparisonValue,
                    '=~' => @\preg_match($comparisonValue, $value1),
                    '>' => $value1 > $comparisonValue,
                    '>=' => $value1 >= $comparisonValue,
                    '<' => $value1 < $comparisonValue,
                    '<=' => $value1 <= $comparisonValue,
                    "in" => \is_array($comparisonValue) && \in_array($value1, $comparisonValue, false),
                    'nin',"!in" =>  \is_array($comparisonValue) && !\in_array($value1, $comparisonValue, false)
                };

                if ($comparisonResult) {
                    $return[] = $value;
                }
            }
        }

        return $return;
    }
}
