<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Test;

class JSONPathTestClass
{
    protected array $attributes = [
        'foo' => 'bar',
    ];

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get(mixed $key): ?string
    {
        return $this->attributes[$key] ?? null;
    }
}
