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
    public const string END = 'end';

    public const string IDENTIFIER = 'identifier';

    public const string LITERAL = 'literal';

    public const string QUERY = 'query';

    public const string LEFT_PARENTHESIS = 'left-parenthesis';

    public const string RIGHT_PARENTHESIS = 'right-parenthesis';

    public const string COMMA = 'comma';

    public const string OPERATOR = 'operator';

    public function __construct(
        public string $type,
        public mixed $value,
        public int $position,
    ) {
    }
}
