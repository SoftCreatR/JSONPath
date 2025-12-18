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
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;

class IndexesFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     *
     * @throws JSONPathException
     */
    public function filter(array|object $collection): array
    {
        $return = [];

        foreach ($this->token->value as $index) {
            if (\is_array($index) && ($index['type'] ?? null) === 'slice') {
                $sliceToken = new JSONPathToken(TokenType::Slice, $index['value']);
                $sliceFilter = new SliceFilter(
                    $sliceToken,
                    $this->magicIsAllowed ? JSONPath::ALLOW_MAGIC : 0
                );
                $sliceFilter->setRootData($this->rootData ?? $collection);

                $return = \array_merge($return, $sliceFilter->filter($collection));

                continue;
            }

            if (\is_array($index) && ($index['type'] ?? null) === 'query') {
                $queryToken = new JSONPathToken(TokenType::QueryMatch, $index['value']);
                $queryFilter = new QueryMatchFilter(
                    $queryToken,
                    $this->magicIsAllowed ? JSONPath::ALLOW_MAGIC : 0
                );
                $queryFilter->setRootData($this->rootData ?? $collection);

                $return = \array_merge($return, $queryFilter->filter($collection));

                continue;
            }

            if ($index === '*' && !$this->token->quoted) {
                $return = \array_merge($return, AccessHelper::arrayValues($collection));

                continue;
            }

            if (AccessHelper::keyExists($collection, $index, $this->magicIsAllowed)) {
                $return[] = AccessHelper::getValue($collection, $index, $this->magicIsAllowed);
            }
        }

        return $return;
    }
}
