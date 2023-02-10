<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath;

use ArrayAccess;

class AccessHelper
{
    public static function collectionKeys(mixed $collection): array
    {
        if (\is_object($collection)) {
            return \array_keys(\get_object_vars($collection));
        }

        return \array_keys($collection);
    }

    public static function isCollectionType(mixed $collection): bool
    {
        return \is_array($collection) || \is_object($collection);
    }

    public static function keyExists(mixed $collection, $key, bool $magicIsAllowed = false): bool
    {
        if ($magicIsAllowed && \is_object($collection) && \method_exists($collection, '__get')) {
            return true;
        }

        if (\is_int($key) && $key < 0) {
            $key = \abs($key);
        }

        if (\is_array($collection)) {
            return \array_key_exists($key, $collection);
        }

        if ($collection instanceof ArrayAccess) {
            return $collection->offsetExists($key);
        }

        if (\is_object($collection)) {
            return \property_exists($collection, (string)$key);
        }

        return false;
    }

    /**
     * @todo Optimize conditions
     */
    public static function getValue(mixed $collection, $key, bool $magicIsAllowed = false)
    {
        if (
            $magicIsAllowed
            && \is_object($collection)
            && !$collection instanceof ArrayAccess && \method_exists($collection, '__get')
        ) {
            $return = $collection->__get($key);
        } elseif (\is_object($collection) && !$collection instanceof ArrayAccess) {
            $return = $collection->{$key};
        } elseif ($collection instanceof ArrayAccess) {
            $return = $collection->offsetGet($key);
        } elseif (\is_array($collection)) {
            if (\is_int($key) && $key < 0) {
                $return = \array_slice($collection, $key, 1)[0];
            } else {
                $return = $collection[$key];
            }
        } elseif (\is_int($key)) {
            $return = self::getValueByIndex($collection, $key);
        } else {
            $return = $collection[$key];
        }

        return $return;
    }

    /**
     * Find item in php collection by index
     * Written this way to handle instances ArrayAccess or Traversable objects
     */
    private static function getValueByIndex(mixed $collection, $key): mixed
    {
        $i = 0;

        foreach ($collection as $val) {
            if ($i === $key) {
                return $val;
            }

            $i++;
        }

        if ($key < 0) {
            $total = $i;
            $i = 0;

            foreach ($collection as $val) {
                if ($i - $total === $key) {
                    return $val;
                }

                $i++;
            }
        }

        return null;
    }

    public static function setValue(mixed &$collection, $key, $value)
    {
        if (\is_object($collection) && !$collection instanceof ArrayAccess) {
            return $collection->{$key} = $value;
        }

        if ($collection instanceof ArrayAccess) {
            /** @noinspection PhpVoidFunctionResultUsedInspection */
            return $collection->offsetSet($key, $value);
        }

        return $collection[$key] = $value;
    }

    public static function unsetValue(mixed &$collection, $key): void
    {
        if (\is_object($collection) && !$collection instanceof ArrayAccess) {
            unset($collection->{$key});
        }

        if ($collection instanceof ArrayAccess) {
            $collection->offsetUnset($key);
        }

        if (\is_array($collection)) {
            unset($collection[$key]);
        }
    }

    /**
     * @throws JSONPathException
     */
    public static function arrayValues(array|object $collection): array|ArrayAccess
    {
        if (\is_array($collection)) {
            return \array_values($collection);
        }

        if (\is_object($collection)) {
            return \array_values((array)$collection);
        }

        throw new JSONPathException("Invalid variable type for arrayValues");
    }
}
