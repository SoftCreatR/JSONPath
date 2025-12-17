<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPathException;

class QueryResultFilter extends AbstractFilter
{
    /**
     * @throws JSONPathException
     * @inheritDoc
     */
    public function filter(array|object $collection): array
    {
        if (!\preg_match('/@\.(?<key>\w+)\s*(?<operator>[-+*\/])\s*(?<numeric>\d+)/', $this->token->value, $matches)) {
            throw new JSONPathException('Unsupported operator in expression');
        }

        $matchKey = $matches['key'];

        if (AccessHelper::keyExists($collection, $matchKey, $this->magicIsAllowed)) {
            $value = AccessHelper::getValue($collection, $matchKey, $this->magicIsAllowed);
        } elseif ($matches['key'] === 'length') {
            $value = \count($collection);
        } else {
            return [];
        }

        $resultKey = match ($matches['operator']) {
            '+' => $value + $matches['numeric'],
            '*' => $value * $matches['numeric'],
            '-' => $value - $matches['numeric'],
            '/' => $value / $matches['numeric'],
        };

        $result = [];

        if (AccessHelper::keyExists($collection, $resultKey, $this->magicIsAllowed)) {
            $result[] = AccessHelper::getValue($collection, $resultKey, $this->magicIsAllowed);
        }

        return $result;
    }
}
