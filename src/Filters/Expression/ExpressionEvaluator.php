<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

use Flow\JSONPath\JSONPath;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\Nothing;

final readonly class ExpressionEvaluator
{
    public function __construct(
        private mixed $root,
        private int $options = 0,
    ) {
    }

    /**
     * @throws JSONPathException
     */
    public function matches(ExpressionNode $expression, mixed $current): bool
    {
        return $this->evaluateLogical($expression, $current);
    }

    /**
     * @throws JSONPathException
     */
    private function evaluateLogical(ExpressionNode $node, mixed $current): bool
    {
        if ($node->type === ExpressionType::Nodes) {
            return $this->evaluateNodes($node, $current) !== [];
        }

        return match ($node->kind) {
            ExpressionNode::FUNCTION => $this->evaluateFunction($node, $current),
            ExpressionNode::COMPARISON => $this->evaluateComparison($node, $current),
            ExpressionNode::LOGICAL_AND => $this->evaluateLogical($node->children[0], $current)
                && $this->evaluateLogical($node->children[1], $current),
            ExpressionNode::LOGICAL_OR => $this->evaluateLogical($node->children[0], $current)
                || $this->evaluateLogical($node->children[1], $current),
            ExpressionNode::LOGICAL_NOT => !$this->evaluateLogical($node->children[0], $current),
            default => throw new JSONPathException('Invalid logical expression'),
        };
    }

    /**
     * @throws JSONPathException
     */
    private function evaluateComparison(ExpressionNode $node, mixed $current): bool
    {
        $left = $this->evaluateValue($node->children[0], $current);
        $right = $this->evaluateValue($node->children[1], $current);

        return match ($node->value) {
            '==' => $this->compareEquals($left, $right),
            '!=' => !$this->compareEquals($left, $right),
            '<' => $this->compareLessThan($left, $right),
            '<=' => $this->compareLessThan($left, $right) || $this->compareEquals($left, $right),
            '>' => $this->compareLessThan($right, $left),
            '>=' => $this->compareLessThan($right, $left) || $this->compareEquals($left, $right),
            default => throw new JSONPathException('Invalid comparison operator'),
        };
    }

    /**
     * @throws JSONPathException
     */
    private function evaluateValue(ExpressionNode $node, mixed $current): mixed
    {
        if ($node->kind === ExpressionNode::QUERY && $node->singular) {
            $nodes = $this->evaluateNodes($node, $current);

            return \count($nodes) === 1 ? $nodes[0] : Nothing::instance();
        }

        return match ($node->kind) {
            ExpressionNode::LITERAL => $node->value,
            ExpressionNode::FUNCTION => $this->evaluateFunction($node, $current),
            default => throw new JSONPathException('Invalid value expression'),
        };
    }

    /**
     * @return list<mixed>
     * @throws JSONPathException
     */
    private function evaluateNodes(ExpressionNode $node, mixed $current): array
    {
        if ($node->kind !== ExpressionNode::QUERY || !\is_string($node->value)) {
            throw new JSONPathException('Invalid nodes expression');
        }

        if ($node->value === '@') {
            return [$current];
        }

        $isRootQuery = \str_starts_with($node->value, '$');
        $data = $isRootQuery ? $this->root : $current;
        $query = $isRootQuery ? $node->value : '$' . \substr($node->value, 1);
        $resolved = (new JSONPath($data, $this->options))->find($query)->getData();

        return \is_array($resolved) ? \array_values($resolved) : [];
    }

    /**
     * @throws JSONPathException
     */
    private function evaluateFunction(ExpressionNode $node, mixed $current): mixed
    {
        return match ($node->value) {
            'length' => $this->length($this->evaluateValue($node->children[0], $current)),
            'count' => \count($this->evaluateNodes($node->children[0], $current)),
            'match' => $this->regularExpression($node, $current, true),
            'search' => $this->regularExpression($node, $current, false),
            'value' => $this->value($this->evaluateNodes($node->children[0], $current)),
            default => throw new JSONPathException('Invalid function expression'),
        };
    }

    private function length(mixed $value): mixed
    {
        if (\is_string($value)) {
            $length = \preg_match_all('/./us', $value);

            return $length === false ? Nothing::instance() : $length;
        }

        if (\is_array($value)) {
            return \count($value);
        }

        if (\is_object($value) && $value !== Nothing::instance()) {
            return \count(\get_object_vars($value));
        }

        return Nothing::instance();
    }

    /**
     * @param list<mixed> $nodes
     */
    private function value(array $nodes): mixed
    {
        return \count($nodes) === 1 ? $nodes[0] : Nothing::instance();
    }

    /**
     * @throws JSONPathException
     */
    private function regularExpression(ExpressionNode $node, mixed $current, bool $full): bool
    {
        $value = $this->evaluateValue($node->children[0], $current);
        $expression = $this->evaluateValue($node->children[1], $current);

        return \is_string($value)
            && \is_string($expression)
            && IRegexp::matches($value, $expression, $full);
    }

    private function compareEquals(mixed $left, mixed $right): bool
    {
        if ($left === Nothing::instance() || $right === Nothing::instance()) {
            return $left === $right;
        }

        if ($this->isNumber($left) && $this->isNumber($right)) {
            /** @noinspection TypeUnsafeComparisonInspection */
            return $left == $right;
        }

        if (\gettype($left) !== \gettype($right)) {
            return false;
        }

        if ($left === null || \is_scalar($left)) {
            return $left === $right;
        }

        if (\is_array($left) && \is_array($right)) {
            return $this->deepEqual($left, $right);
        }

        return \is_object($left)
            && \is_object($right)
            && $this->deepEqual((array)$left, (array)$right);
    }

    /**
     * @param array<array-key, mixed> $left
     * @param array<array-key, mixed> $right
     */
    private function deepEqual(array $left, array $right): bool
    {
        if (\array_is_list($left) !== \array_is_list($right) || \count($left) !== \count($right)) {
            return false;
        }

        foreach ($left as $key => $value) {
            if (!\array_key_exists($key, $right) || !$this->compareEquals($value, $right[$key])) {
                return false;
            }
        }

        return true;
    }

    private function compareLessThan(mixed $left, mixed $right): bool
    {
        if ((\is_string($left) && \is_string($right)) || ($this->isNumber($left) && $this->isNumber($right))) {
            return $left < $right;
        }

        return false;
    }

    private function isNumber(mixed $value): bool
    {
        return \is_int($value) || \is_float($value);
    }
}
