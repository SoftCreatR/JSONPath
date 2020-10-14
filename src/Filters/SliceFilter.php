<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use function count;

class SliceFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     */
    public function filter($collection)
    {
        $result = [];
        $length = count($collection);
        $start = $this->token->value['start'];
        $end = $this->token->value['end'];
        $step = $this->token->value['step'] ?: 1;

        if ($start === null) {
            $start = 0;
        }

        if ($start < 0) {
            $start = $length + $start;
        }

        if ($end === null) {
            // negative index start means the end is -1, else the end is the last index
            $end = $length;
        }

        if ($end < 0) {
            $end = $length + $end;
        }

        for ($i = $start; $i < $end; $i += $step) {
            $index = $i;

            if ($i < 0) {
                $index = $length + $i;
            }

            if (AccessHelper::keyExists($collection, $index, $this->magicIsAllowed)) {
                $result[] = $collection[$index];
            }
        }

        return $result;
    }
}
