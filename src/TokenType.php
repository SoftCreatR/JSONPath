<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

enum TokenType: string
{
    case Index = 'index';
    case Recursive = 'recursive';
    case QueryResult = 'queryResult';
    case QueryMatch = 'queryMatch';
    case Slice = 'slice';
    case Indexes = 'indexes';
}
