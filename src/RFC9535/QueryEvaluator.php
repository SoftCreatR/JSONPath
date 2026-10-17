<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\RFC9535;

use Flow\JSONPath\Filters\Expression\ExpressionEvaluator;
use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;

/**
 * Evaluates compiled RFC selectors without casting objects into JSON arrays.
 */
final readonly class QueryEvaluator
{
    /**
     * Keeps the original root available to absolute queries inside nested filters.
     */
    public function __construct(private mixed $root)
    {
    }

    /**
     * Evaluates a node list without constructing normalized path strings.
     *
     * @param list<JSONPathToken> $tokens
     * @return list<mixed>
     */
    public function values(array $tokens): array
    {
        return $this->relativeValues($tokens, $this->root);
    }

    /**
     * Evaluates a relative filter query while retaining its enclosing document root.
     *
     * @param list<JSONPathToken> $tokens
     * @return list<mixed>
     */
    public function relativeValues(array $tokens, mixed $current): array
    {
        return \array_column($this->evaluate($tokens, false, $current), 'value');
    }

    /**
     * Evaluates a node list and returns its normalized locations.
     *
     * @param list<JSONPathToken> $tokens
     * @return list<string>
     */
    public function paths(array $tokens): array
    {
        return \array_column($this->evaluate($tokens, true, $this->root), 'path');
    }

    /**
     * Applies segments in node-list order; descendants visit parents before their children.
     *
     * @param list<JSONPathToken> $tokens
     * @return list<array{value: mixed, path: string}>
     */
    private function evaluate(array $tokens, bool $paths, mixed $input): array
    {
        $nodes = [['value' => $input, 'path' => '$']];
        $recursive = false;

        foreach ($tokens as $token) {
            if ($token->type === TokenType::Recursive) {
                $recursive = true;

                continue;
            }

            $selected = [];

            foreach ($nodes as $node) {
                if ($recursive) {
                    $this->descendants($node, $token, $paths, $selected);
                } else {
                    $this->select($node, $token, $paths, $selected);
                }
            }

            $nodes = $selected;
            $recursive = false;
        }

        return $nodes;
    }

    /**
     * Visits containers with their original values and locations intact.
     *
     * @param array{value: mixed, path: string} $node
     * @param list<array{value: mixed, path: string}> $result
     */
    private function descendants(array $node, JSONPathToken $token, bool $paths, array &$result): void
    {
        $this->select($node, $token, $paths, $result);

        foreach ($this->children($node, $paths) as $child) {
            $this->descendants($child, $token, $paths, $result);
        }
    }

    /**
     * Applies one selector or an ordered union to one input node.
     *
     * @param array{value: mixed, path: string} $node
     * @param list<array{value: mixed, path: string}> $result
     */
    private function select(array $node, JSONPathToken $token, bool $paths, array &$result): void
    {
        if ($token->type === TokenType::Indexes) {
            foreach ($token->value as $selector) {
                $this->select($node, $selector, $paths, $result);
            }

            return;
        }

        if ($token->type === TokenType::QueryMatch) {
            $evaluator = new ExpressionEvaluator($this->root, JSONPath::RFC9535);

            foreach ($this->children($node, $paths) as $child) {
                if ($evaluator->matches($token->value, $child['value'])) {
                    $result[] = $child;
                }
            }

            return;
        }

        if ($token->type === TokenType::Slice) {
            $this->slice($node, $token->value, $paths, $result);

            return;
        }

        if (!$token->quoted && $token->value === '*') {
            foreach ($this->children($node, $paths) as $child) {
                $result[] = $child;
            }

            return;
        }

        $value = $node['value'];
        $key = $token->value;

        if ($token->quoted) {
            $members = \is_object($value) ? \get_object_vars($value) : [];

            if (\array_key_exists($key, $members)) {
                $result[] = ['value' => $members[$key], 'path' => $paths ? $node['path'] . StringLiteral::normalized($key) : ''];
            }
        } elseif (\is_array($value) && \array_is_list($value)) {
            $index = $key < 0 ? \count($value) + $key : $key;

            if (\array_key_exists($index, $value)) {
                $result[] = ['value' => $value[$index], 'path' => $paths ? $node['path'] . '[' . $index . ']' : ''];
            }
        }
    }

    /**
     * Iterates immediate children in the container's iteration order.
     *
     * @param array{value: mixed, path: string} $node
     * @return \Generator<int, array{value: mixed, path: string}>
     */
    private function children(array $node, bool $paths): \Generator
    {
        $value = $node['value'];

        if (\is_object($value)) {
            foreach (\get_object_vars($value) as $key => $child) {
                yield ['value' => $child, 'path' => $paths ? $node['path'] . StringLiteral::normalized((string)$key) : ''];
            }
        } elseif (\is_array($value) && \array_is_list($value)) {
            foreach ($value as $index => $child) {
                yield ['value' => $child, 'path' => $paths ? $node['path'] . '[' . $index . ']' : ''];
            }
        }
    }

    /**
     * Applies bounded positive or negative slices; a zero step selects nothing.
     *
     * @param array{value: mixed, path: string} $node
     * @param array{start: ?int, end: ?int, step: ?int} $slice
     * @param list<array{value: mixed, path: string}> $result
     */
    private function slice(array $node, array $slice, bool $paths, array &$result): void
    {
        $value = $node['value'];
        $step = $slice['step'] ?? 1;

        if (!\is_array($value) || !\array_is_list($value) || $step === 0) {
            return;
        }

        $length = \count($value);
        $start = $slice['start'] ?? ($step > 0 ? 0 : $length - 1);
        $end = $slice['end'] ?? ($step > 0 ? $length : -$length - 1);
        $start = $start < 0 ? $length + $start : $start;
        $end = $end < 0 ? $length + $end : $end;
        $minimum = $step > 0 ? 0 : -1;
        $maximum = $step > 0 ? $length : $length - 1;
        $start = \max($minimum, \min($maximum, $start));
        $end = \max($minimum, \min($maximum, $end));

        for ($index = $start; $step > 0 ? $index < $end : $index > $end; $index += $step) {
            $result[] = ['value' => $value[$index], 'path' => $paths ? $node['path'] . '[' . $index . ']' : ''];
        }
    }
}
