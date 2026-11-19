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
    /**
     * @var string
     */
    public const LITERAL = 'literal';

    /**
     * @var string
     */
    public const QUERY = 'query';

    /**
     * @var string
     */
    public const FUNCTION = 'function';

    /**
     * @var string
     */
    public const COMPARISON = 'comparison';

    /**
     * @var string
     */
    public const LOGICAL_AND = 'logical-and';

    /**
     * @var string
     */
    public const LOGICAL_OR = 'logical-or';

    /**
     * @var string
     */
    public const LOGICAL_NOT = 'logical-not';

    /**
     * @var string
     */
    public const GROUP = 'group';

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
