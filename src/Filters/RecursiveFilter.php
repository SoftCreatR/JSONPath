<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\AccessHelper;
use Flow\JSONPath\JSONPathException;

class RecursiveFilter extends AbstractFilter
{
    /**
     * @inheritDoc
     * @throws JSONPathException
     */
    public function filter($collection)
    {
        $result = [];

        $this->recurse($result, $collection);

        return $result;
    }

    /**
     * @param $result
     * @param $data
     * @throws JSONPathException
     */
    private function recurse(&$result, $data)
    {
        $result[] = $data;

        if (AccessHelper::isCollectionType($data)) {
            foreach (AccessHelper::arrayValues($data) as $key => $value) {
                $results[] = $value;

                if (AccessHelper::isCollectionType($value)) {
                    $this->recurse($result, $value);
                }
            }
        }
    }
}
