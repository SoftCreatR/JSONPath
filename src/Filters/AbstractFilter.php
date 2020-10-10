<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */
declare(strict_types=1);

namespace Flow\JSONPath\Filters;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathToken;

abstract class AbstractFilter
{
    /**
     * @var JSONPathToken
     */
    protected $token;

    /**
     * @var  int
     */
    protected $options;

    /**
     * @var  bool
     */
    protected $magicIsAllowed;

    /**
     * AbstractFilter constructor.
     *
     * @param JSONPathToken $token
     * @param int $options
     */
    public function __construct(JSONPathToken $token, $options = 0)
    {
        $this->token = $token;
        $this->options = $options;
        $this->magicIsAllowed = $this->options & JSONPath::ALLOW_MAGIC;
    }

    /**
     * @param array|object $collection
     * @return array
     */
    abstract public function filter($collection): array;
}
