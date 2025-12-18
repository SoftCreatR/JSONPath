<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

use Flow\JSONPath\Filters\AbstractFilter;
use Flow\JSONPath\Filters\IndexesFilter;
use Flow\JSONPath\Filters\IndexFilter;
use Flow\JSONPath\Filters\QueryMatchFilter;
use Flow\JSONPath\Filters\QueryResultFilter;
use Flow\JSONPath\Filters\RecursiveFilter;
use Flow\JSONPath\Filters\SliceFilter;

readonly class JSONPathToken
{
    public function __construct(
        public TokenType $type,
        public mixed $value,
        public bool $quoted = false,
        public bool $shorthand = false,
    ) {
        // ...
    }

    public function buildFilter(int $options): AbstractFilter
    {
        $filterClass = match ($this->type) {
            TokenType::Index => IndexFilter::class,
            TokenType::Indexes => IndexesFilter::class,
            TokenType::QueryMatch => QueryMatchFilter::class,
            TokenType::QueryResult => QueryResultFilter::class,
            TokenType::Recursive => RecursiveFilter::class,
            TokenType::Slice => SliceFilter::class,
        };

        return new $filterClass($this, $options);
    }
}
