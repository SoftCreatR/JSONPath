<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

use ArrayAccess;
use Traversable;

class AccessHelper
{
    /**
     * @return array<int, int|string>
     */
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

    public static function keyExists(mixed $collection, int|string|null $key, bool $magicIsAllowed = false): bool
    {
        if ($magicIsAllowed && \is_object($collection) && \method_exists($collection, '__get')) {
            return true;
        }

        if (\is_array($collection)) {
            if (\is_int($key) && $key < 0) {
                $keys = \array_keys($collection);
                $index = \count($keys) + $key;

                return $index >= 0 && \array_key_exists($index, $keys);
            }

            return \array_key_exists($key ?? '', $collection);
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
    public static function getValue(mixed $collection, int|string|null $key, bool $magicIsAllowed = false): mixed
    {
        if (
            $magicIsAllowed
            && \is_object($collection)
            && !$collection instanceof ArrayAccess && \method_exists($collection, '__get')
        ) {
            $return = $collection->__get($key);
        } elseif (\is_int($key) && $collection instanceof Traversable && !$collection instanceof ArrayAccess) {
            $return = self::getValueByIndex($collection, $key);
        } elseif (\is_object($collection) && !$collection instanceof ArrayAccess) {
            $return = $collection->{$key};
        } elseif ($collection instanceof ArrayAccess) {
            $return = $collection->offsetExists($key) ? $collection->offsetGet($key) : null;
        } elseif (\is_array($collection)) {
            if (\is_int($key) && $key < 0) {
                $index = \count($collection) + $key;
                $return = $index >= 0 && \array_key_exists($index, $collection) ? $collection[$index] : null;
            } else {
                $return = $collection[$key] ?? null;
            }
        } else {
            $return = null;
        }

        return $return;
    }

    /**
     * Find item in php collection by index
     * Written this way to handle instances ArrayAccess or Traversable objects
     */
    private static function getValueByIndex(mixed $collection, int $key): mixed
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

    public static function setValue(mixed &$collection, int|string|null $key, mixed $value): mixed
    {
        if (\is_object($collection) && !$collection instanceof ArrayAccess) {
            $collection->{$key} = $value;

            return $value;
        }

        if ($collection instanceof ArrayAccess) {
            $collection->offsetSet($key, $value);

            return $value;
        }

        $collection[$key] = $value;

        return $value;
    }

    public static function unsetValue(mixed &$collection, int|string|null $key): void
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
    /**
     * @return array<int, mixed>
     * @throws JSONPathException
     */
    public static function arrayValues(mixed $collection): array
    {
        if (\is_array($collection)) {
            return \array_values($collection);
        }

        if (\is_object($collection)) {
            return \array_values((array)$collection);
        }

        throw new JSONPathException('Invalid variable type for arrayValues');
    }
}
