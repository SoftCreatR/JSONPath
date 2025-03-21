<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath\Test\Traits;

use JsonException;
use RuntimeException;

use const JSON_THROW_ON_ERROR;

trait TestDataTrait
{
    /**
     * Returns decoded JSON from a given file either as array or object.
     *
     * @throws RuntimeException
     */
    protected function getData(string $type, bool|int $asArray = true): mixed
    {
        $filePath = \sprintf('%s/data/%s.json', \dirname(__DIR__), $type);

        if (!\file_exists($filePath)) {
            throw new RuntimeException("File {$filePath} does not exist.");
        }

        try {
            $json = \json_decode(\file_get_contents($filePath), (bool)$asArray, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException("File {$filePath} does not contain valid JSON. Error: {$e->getMessage()}");
        }

        return $json;
    }
}
