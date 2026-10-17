<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

use Flow\JSONPath\JSONPathToken;

final readonly class ExpressionNode
{
    public const string LITERAL = 'literal';

    public const string QUERY = 'query';

    public const string FUNCTION = 'function';

    public const string COMPARISON = 'comparison';

    public const string LOGICAL_AND = 'logical-and';

    public const string LOGICAL_OR = 'logical-or';

    public const string LOGICAL_NOT = 'logical-not';

    public const string GROUP = 'group';

    /**
     * @param list<self> $children
     * @param list<JSONPathToken> $queryTokens
     */
    public function __construct(
        public string $kind,
        public ExpressionType $type,
        public mixed $value = null,
        public array $children = [],
        public bool $singular = false,
        public array $queryTokens = [],
    ) {
    }
}
