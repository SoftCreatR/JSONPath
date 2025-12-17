<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;
use Override;

/**
 * @implements ArrayAccess<int|string, mixed>
 * @implements Iterator<int|string, mixed>
 */
class JSONPath implements ArrayAccess, Iterator, JsonSerializable, Countable
{
    public const int ALLOW_MAGIC = 1;

    /** @var array<int, list<JSONPathToken>> */
    protected static array $tokenCache = [];

    protected mixed $data = [];

    protected int $options = 0;

    final public function __construct(mixed $data = [], int $options = 0)
    {
        $this->data = $data;
        $this->options = $options;
    }

    /**
     * Evaluate an expression
     *
     * @throws JSONPathException
     *
     * @return static
     */
    public function find(string $expression): self
    {
        $tokens = $this->parseTokens($expression);
        $collectionData = [$this->data];

        foreach ($tokens as $token) {
            $filter = $token->buildFilter($this->options);
            $filteredDataList = [];

            foreach ($collectionData as $value) {
                if (AccessHelper::isCollectionType($value)) {
                    $filteredDataList[] = $filter->filter($value);
                }
            }

            if (!empty($filteredDataList)) {
                $collectionData = \array_merge(...$filteredDataList);
            } else {
                $collectionData = [];
            }
        }

        return new static($collectionData, $this->options);
    }

    public function first(): mixed
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        $value = $this->data[$keys[0]] ?? null;

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * Evaluate an expression and return the last result
     */
    public function last(): mixed
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        $value = $this->data[\end($keys)] ?? null;

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * Evaluate an expression and return the first key
     */
    public function firstKey(): string|int|null
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        return $keys[0];
    }

    /**
     * Evaluate an expression and return the last key
     */
    public function lastKey(): string|int|null
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys)) {
            return null;
        }

        return \end($keys);
    }

    /**
     * @return list<JSONPathToken>
     * @throws JSONPathException
     */
    public function parseTokens(string $expression): array
    {
        $cacheKey = \crc32($expression);

        if (isset(static::$tokenCache[$cacheKey])) {
            return static::$tokenCache[$cacheKey];
        }

        $lexer = new JSONPathLexer($expression);
        $tokens = $lexer->parseExpression();

        static::$tokenCache[$cacheKey] = $tokens;

        return $tokens;
    }

    public function getData(): mixed
    {
        return $this->data;
    }

    /**
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get(string|int $key): mixed
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : null;
    }

    #[Override]
    public function offsetExists(mixed $offset): bool
    {
        return AccessHelper::keyExists($this->data, $offset);
    }

    #[Override]
    public function offsetGet(mixed $offset): mixed
    {
        $value = AccessHelper::getValue($this->data, $offset);

        return AccessHelper::isCollectionType($value)
            ? new static($value, $this->options)
            : $value;
    }

    #[Override]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            AccessHelper::setValue($this->data, $offset, $value);
        }
    }

    #[Override]
    public function offsetUnset(mixed $offset): void
    {
        AccessHelper::unsetValue($this->data, $offset);
    }

    #[Override]
    public function jsonSerialize(): mixed
    {
        return $this->getData();
    }

    #[Override]
    public function current(): mixed
    {
        $value = \current($this->data);

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    #[Override]
    public function next(): void
    {
        \next($this->data);
    }

    #[Override]
    public function key(): string|int|null
    {
        return \key($this->data);
    }

    #[Override]
    public function valid(): bool
    {
        return \key($this->data) !== null;
    }

    #[Override]
    public function rewind(): void
    {
        \reset($this->data);
    }

    #[Override]
    public function count(): int
    {
        return \count($this->data);
    }
}
