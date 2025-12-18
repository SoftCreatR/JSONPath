<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathToken;

abstract class AbstractFilter
{
    protected bool $magicIsAllowed;

    protected mixed $rootData = null;

    public function __construct(protected JSONPathToken $token, int $options = 0)
    {
        $this->magicIsAllowed = ($options & JSONPath::ALLOW_MAGIC) === JSONPath::ALLOW_MAGIC;
    }

    public function setRootData(mixed $root): void
    {
        $this->rootData = $root;
    }

    /**
     * @param array<array-key, mixed>|object $collection
     * @return array<array-key, mixed>
     */
    abstract public function filter(array|object $collection): array;
}
