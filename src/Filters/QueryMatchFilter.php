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
use function error_log;

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
            $comparisonValue = \preg_replace('/^[\']/', '"', $comparisonValue);
            $comparisonValue = \preg_replace('/[\']$/', '"', $comparisonValue);
            try {
                $comparisonValue = \json_decode($comparisonValue, true, flags:JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                //Leave $comparisonValue as raw (regular express or non quote wrapped string
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

            $comparisonResult = match ($operator) {
                null => AccessHelper::keyExists($value, $key, $this->magicIsAllowed),
                "=","==" => $value1 === $comparisonValue,
                "!=","!==","<>" => $value1 !== $comparisonValue,
                '=~' => @\preg_match($comparisonValue, $value1),
                '>' => $value1 > $comparisonValue,
                '>=' => $value1 >= $comparisonValue,
                '<' => $value1 < $comparisonValue,
                '<=' => $value1 <= $comparisonValue,
                "in" => \is_array($comparisonValue) && \in_array($value1, $comparisonValue, true),
                'nin',"!in" =>  \is_array($comparisonValue) && !\in_array($value1, $comparisonValue, true)
            };

            if ($comparisonResult) {
                $return[] = $value;
            }
        }

        return $return;
    }
}
