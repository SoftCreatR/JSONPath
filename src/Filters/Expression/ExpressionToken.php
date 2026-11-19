<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

final readonly class ExpressionToken
{
    /**
     * @var string
     */
    public const END = 'end';

    /**
     * @var string
     */
    public const IDENTIFIER = 'identifier';

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
    public const LEFT_PARENTHESIS = 'left-parenthesis';

    /**
     * @var string
     */
    public const RIGHT_PARENTHESIS = 'right-parenthesis';

    /**
     * @var string
     */
    public const COMMA = 'comma';

    /**
     * @var string
     */
    public const OPERATOR = 'operator';

    public function __construct(
        public string $type,
        public mixed $value,
        public int $position,
    ) {
    }
}
