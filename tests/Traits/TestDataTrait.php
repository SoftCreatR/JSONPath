<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath\Test\Traits;

use JsonException;
use RuntimeException;

use const JSON_ERROR_NONE;
use const JSON_THROW_ON_ERROR;

trait TestDataTrait
{
    /**
     * Returns decoded JSON from a given file either as array or object.
     *
     * @throws JsonException
     */
    protected function getData(string $type, bool|int $asArray = true): mixed
    {
        $filePath = \sprintf('%s/data/%s.json', \dirname(__DIR__), $type);

        if (!\file_exists($filePath)) {
            throw new RuntimeException("File {$filePath} does not exist.");
        }

        $json = \json_decode(\file_get_contents($filePath), (bool)$asArray, 512, JSON_THROW_ON_ERROR);

        if (\json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("File {$filePath} does not contain valid JSON.");
        }

        return $json;
    }
}
