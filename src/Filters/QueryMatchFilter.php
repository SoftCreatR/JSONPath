<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath\Filters;

use RuntimeException;
use Flow\JSONPath\AccessHelper;
use function is_string;
use function preg_match;
use function preg_replace;
use function strtolower;

class QueryMatchFilter extends AbstractFilter
{
    const MATCH_QUERY_OPERATORS = '
      @(\.(?<key>[^ =]+)|\[["\']?(?<keySquare>.*?)["\']?\])
      (\s*(?<operator>==|=|<>|!==|!=|>|<)\s*(?<comparisonValue>.+))?
    ';

    /**
     * @inheritDoc
     * @return array
     */
    public function filter($collection)
    {
        $return = [];

        preg_match('/^' . static::MATCH_QUERY_OPERATORS . '$/x', $this->token->value, $matches);

        if (!isset($matches[1])) {
            throw new RuntimeException('Malformed filter query');
        }

        $key = $matches['key'] ?: $matches['keySquare'];

        if ($key === '') {
            throw new RuntimeException('Malformed filter query: key was not set');
        }

        $operator = isset($matches['operator']) ? $matches['operator'] : null;
        $comparisonValue   = isset($matches['comparisonValue']) ? $matches['comparisonValue'] : null;

        if (is_string(($comparisonValue))) {
            if (strtolower($comparisonValue) === 'false') {
                $comparisonValue = false;
            }

            if (strtolower($comparisonValue) === 'true') {
                $comparisonValue = true;
            }

            if (strtolower($comparisonValue) === 'null') {
                $comparisonValue = null;
            }

            $comparisonValue = preg_replace('/^[\'"]/', '', $comparisonValue);
            $comparisonValue = preg_replace('/[\'"]$/', '', $comparisonValue);
        }

        foreach ($collection as $value) {
            if (AccessHelper::keyExists($value, $key, $this->magicIsAllowed)) {
                $value1 = AccessHelper::getValue($value, $key, $this->magicIsAllowed);

                if ($operator === null && $value1) {
                    $return[] = $value;
                }

                if (($operator === '=' || $operator === '==') && (string)$value1 === (string)$comparisonValue) {
                    $return[] = $value;
                }

                if (($operator === '!=' || $operator === '!==' || $operator === '<>') && (string)$value1 !== (string)$comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '>' && $value1 > $comparisonValue) {
                    $return[] = $value;
                }

                if ($operator === '<' && $value1 < $comparisonValue) {
                    $return[] = $value;
                }
            }
        }

        return $return;
    }
}
