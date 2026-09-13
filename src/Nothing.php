<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

final class Nothing
{
    private function __construct()
    {
    }

    public static function instance(): self
    {
        static $instance = new self();

        return $instance;
    }
}
