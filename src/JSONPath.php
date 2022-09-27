<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

namespace Flow\JSONPath;

use ArrayAccess;
use Countable;
use Iterator;
use JsonSerializable;

class JSONPath implements ArrayAccess, Iterator, JsonSerializable, Countable
{
    public const ALLOW_MAGIC = true;

    protected static array $tokenCache = [];

    protected mixed $data = [];

    protected bool $options = false;

    final public function __construct(mixed $data = [], bool $options = false)
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
            /** @var JSONPathToken $token */
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

        $value = $this->data[\end($keys)] ?: null;

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * Evaluate an expression and return the first key
     */
    public function firstKey(): mixed
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
    public function lastKey(): mixed
    {
        $keys = AccessHelper::collectionKeys($this->data);

        if (empty($keys) || \end($keys) === false) {
            return null;
        }

        return \end($keys);
    }

    /**
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

    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @return mixed|null
     * @noinspection MagicMethodsValidityInspection
     */
    public function __get($key)
    {
        return $this->offsetExists($key) ? $this->offsetGet($key) : null;
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return AccessHelper::keyExists($this->data, $offset);
    }

    /**
     * @inheritDoc
     */
    public function offsetGet($offset): mixed
    {
        $value = AccessHelper::getValue($this->data, $offset);

        return AccessHelper::isCollectionType($value)
            ? new static($value, $this->options)
            : $value;
    }

    /**
     * @inheritDoc
     */
    public function offsetSet($offset, $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
        } else {
            AccessHelper::setValue($this->data, $offset, $value);
        }
    }

    /**
     * @inheritDoc
     */
    public function offsetUnset($offset): void
    {
        AccessHelper::unsetValue($this->data, $offset);
    }

    /**
     * @inheritDoc
     */
    public function jsonSerialize(): array
    {
        return $this->getData();
    }

    /**
     * @inheritDoc
     */
    public function current(): mixed
    {
        $value = \current($this->data);

        return AccessHelper::isCollectionType($value) ? new static($value, $this->options) : $value;
    }

    /**
     * @inheritDoc
     */
    public function next(): void
    {
        \next($this->data);
    }

    /**
     * @inheritDoc
     */
    public function key(): string|int|null
    {
        return \key($this->data);
    }

    /**
     * @inheritDoc
     */
    public function valid(): bool
    {
        return \key($this->data) !== null;
    }

    /**
     * @inheritDoc
     */
    public function rewind(): void
    {
        \reset($this->data);
    }

    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return \count($this->data);
    }
}
