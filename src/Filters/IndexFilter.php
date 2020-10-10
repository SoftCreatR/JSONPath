<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */
declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPathException;

class IndexFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     * @throws JSONPathException
     */
    public function filter($collection): array
    {
        if (AccessHelper::keyExists($collection, $this->token->value, $this->magicIsAllowed)) {
            return [
                AccessHelper::getValue($collection, $this->token->value, $this->magicIsAllowed)
            ];
        }

        if ($this->token->value === '*') {
            return AccessHelper::arrayValues($collection);
        }

        return [];
    }
}
