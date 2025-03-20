<?php declare(strict_types=1);

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
use function is_scalar;
use function is_string;

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

            $notNothing = AccessHelper::keyExists($value, $key, $this->magicIsAllowed);
            if ($notNothing) {
                $value1 = AccessHelper::getValue($value, $key, $this->magicIsAllowed);
            } elseif (\str_contains($key, '.')) {
                $foundValue = (new JSONPath($value))->find($key)->getData();
                if ($foundValue) {
                    $value1 = $foundValue[0];
                    $notNothing = true;
                }
            }

            $comparisonResult = null;
            if ($notNothing) {
                $comparisonResult = match ($operator) {
                    null => AccessHelper::keyExists($value, $key, $this->magicIsAllowed),
                    "=", "==" => $this->compareEquals($value1, $comparisonValue),
                    "!=", "!==", "<>" => !$this->compareEquals($value1, $comparisonValue),
                    '=~' => @\preg_match($comparisonValue, $value1),
                    '<' => $this->compareLessThan($value1, $comparisonValue),
                    '<=' => $this->compareLessThan($value1, $comparisonValue)
                        || $this->compareEquals($value1, $comparisonValue),
                    '>' => $this->compareLessThan($comparisonValue, $value1), //rfc semantics
                    '>=' => $this->compareLessThan($comparisonValue, $value1) //rfc semantics
                        || $this->compareEquals($value1, $comparisonValue),
                    "in" => \is_array($comparisonValue) && \in_array($value1, $comparisonValue, true),
                    'nin', "!in" => \is_array($comparisonValue) && !\in_array($value1, $comparisonValue, true)
                };
            }

            if ($comparisonResult) {
                $return[] = $value;
            }
        }

        return $return;
    }

    protected function isNumber($value): bool
    {
        return !is_string($value) && \is_numeric($value);
    }

    protected function compareEquals($a, $b): bool
    {
        $type_a = gettype($a);
        $type_b = gettype($b);
        if ($type_a === $type_b || ($this->isNumber($a) && $this->isNumber($b))) {
            //Primitives or Numbers
            if ($a === null || is_scalar($a)) {
                return $a == $b;
            }
            //Object/Array
            //@TODO array and object comparison
        }
        return false;
    }

    protected function compareLessThan($a, $b): bool
    {
        if ((is_string($a) && is_string($b)) || ($this->isNumber($a) && $this->isNumber($b))) {
            //numerical and string comparison supported only
            return $a < $b;
        }
        return false;
    }
}
