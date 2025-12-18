<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPathException;

class RecursiveFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     *
     * @throws JSONPathException
     */
    public function filter(array|object $collection): array
    {
        $result = [];

        $this->recurse($result, $collection);

        return $result;
    }

    /**
     * @param array<int, array<array-key, mixed>> $result
     * @param array<array-key, mixed>|object $data
     *
     * @throws JSONPathException
     */
    private function recurse(array &$result, array|object $data): void
    {
        $result[] = (array)$data;

        if (AccessHelper::isCollectionType($data)) {
            foreach (AccessHelper::arrayValues($data) as $value) {
                if (AccessHelper::isCollectionType($value)) {
                    $this->recurse($result, $value);
                }
            }
        }
    }
}
