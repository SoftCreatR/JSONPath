<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */

namespace Flow\JSONPath;


use function class_exists;
use function in_array;
use function ucfirst;

class JSONPathToken
{
    /*
     * Tokens
     */
    const T_INDEX = 'index';
    const T_RECURSIVE = 'recursive';
    const T_QUERY_RESULT = 'queryResult';
    const T_QUERY_MATCH = 'queryMatch';
    const T_SLICE = 'slice';
    const T_INDEXES = 'indexes';

    public $type;
    public $value;

    /**
     * JSONPathToken constructor.
     *
     * @param $type
     * @param $value
     * @throws JSONPathException
     */
    public function __construct($type, $value)
    {
        $this->validateType($type);

        $this->type = $type;
        $this->value = $value;
    }

    /**
     * @param $type
     * @throws JSONPathException
     */
    public function validateType($type)
    {
        if (!in_array($type, static::getTypes(), true)) {
            throw new JSONPathException('Invalid token: ' . $type);
        }
    }

    /**
     * @return string[]
     */
    public static function getTypes()
    {
        return [
            static::T_INDEX,
            static::T_RECURSIVE,
            static::T_QUERY_RESULT,
            static::T_QUERY_MATCH,
            static::T_SLICE,
            static::T_INDEXES,
        ];
    }

    /**
     * @param $options
     * @return mixed
     * @throws JSONPathException
     */
    public function buildFilter($options)
    {
        $filterClass = 'Flow\\JSONPath\\Filters\\' . ucfirst($this->type) . 'Filter';

        if (!class_exists($filterClass)) {
            throw new JSONPathException("No filter class exists for token [{$this->type}]");
        }

        return new $filterClass($this, $options);
    }
}
