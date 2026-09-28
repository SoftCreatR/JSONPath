<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

use Flow\JSONPath\JSONPathException;

final class ExpressionParser
{
    /** @var list<ExpressionToken> */
    private array $tokens;

    private int $position = 0;

    /**
     * @throws JSONPathException
     */
    public function __construct(string $expression)
    {
        $this->tokens = (new ExpressionLexer($expression))->tokenize();
    }

    /**
     * @throws JSONPathException
     */
    public function parse(): ExpressionNode
    {
        $expression = $this->parseOr();
        $this->expect(ExpressionToken::END);
        $this->assertLogical($expression, 'Filter expression');

        return $expression;
    }

    /**
     * @throws JSONPathException
     */
    private function parseOr(): ExpressionNode
    {
        $left = $this->parseAnd();

        while ($this->match(ExpressionToken::OPERATOR, '||')) {
            $right = $this->parseAnd();
            $this->assertLogical($left, 'Left operand of `||`');
            $this->assertLogical($right, 'Right operand of `||`');
            $left = new ExpressionNode(
                ExpressionNode::LOGICAL_OR,
                ExpressionType::Logical,
                children: [$left, $right],
            );
        }

        return $left;
    }

    /**
     * @throws JSONPathException
     */
    private function parseAnd(): ExpressionNode
    {
        $left = $this->parseUnary();

        while ($this->match(ExpressionToken::OPERATOR, '&&')) {
            $right = $this->parseUnary();
            $this->assertLogical($left, 'Left operand of `&&`');
            $this->assertLogical($right, 'Right operand of `&&`');
            $left = new ExpressionNode(
                ExpressionNode::LOGICAL_AND,
                ExpressionType::Logical,
                children: [$left, $right],
            );
        }

        return $left;
    }

    /**
     * @throws JSONPathException
     */
    private function parseUnary(): ExpressionNode
    {
        if (!$this->match(ExpressionToken::OPERATOR, '!')) {
            return $this->parseComparison();
        }

        $operand = $this->parseUnary();
        $this->assertLogical($operand, 'Operand of `!`');

        return new ExpressionNode(
            ExpressionNode::LOGICAL_NOT,
            ExpressionType::Logical,
            children: [$operand],
        );
    }

    /**
     * @throws JSONPathException
     */
    private function parseComparison(): ExpressionNode
    {
        $left = $this->parsePrimary();
        $operator = $this->current();

        if (
            $operator->type !== ExpressionToken::OPERATOR
            || !\in_array($operator->value, ['==', '!=', '<', '<=', '>', '>='], true)
        ) {
            return $left;
        }

        $this->position++;
        $right = $this->parsePrimary();
        $this->assertValue($left, 'Left comparison operand');
        $this->assertValue($right, 'Right comparison operand');

        return new ExpressionNode(
            ExpressionNode::COMPARISON,
            ExpressionType::Logical,
            $operator->value,
            [$left, $right],
        );
    }

    /**
     * @throws JSONPathException
     */
    private function parsePrimary(): ExpressionNode
    {
        $token = $this->current();

        if ($token->type === ExpressionToken::LITERAL) {
            $this->position++;

            return new ExpressionNode(ExpressionNode::LITERAL, ExpressionType::Value, $token->value);
        }

        if ($token->type === ExpressionToken::QUERY) {
            $this->position++;

            return new ExpressionNode(
                ExpressionNode::QUERY,
                ExpressionType::Nodes,
                $token->value,
                singular: $this->isSingularQuery($token->value),
            );
        }

        if ($token->type === ExpressionToken::IDENTIFIER) {
            return $this->parseFunction();
        }

        if ($this->match(ExpressionToken::LEFT_PARENTHESIS)) {
            $expression = $this->parseOr();
            $this->expect(ExpressionToken::RIGHT_PARENTHESIS);

            return $expression;
        }

        throw new JSONPathException("Expected an expression at position {$token->position}");
    }

    /**
     * @throws JSONPathException
     */
    private function parseFunction(): ExpressionNode
    {
        $name = $this->expect(ExpressionToken::IDENTIFIER)->value;
        $this->expect(ExpressionToken::LEFT_PARENTHESIS);
        $arguments = [];

        if (!$this->check(ExpressionToken::RIGHT_PARENTHESIS)) {
            do {
                $arguments[] = $this->parseOr();
            } while ($this->match(ExpressionToken::COMMA));
        }

        $this->expect(ExpressionToken::RIGHT_PARENTHESIS);
        [$parameters, $result] = $this->signature($name);

        if (\count($arguments) !== \count($parameters)) {
            throw new JSONPathException(
                "Function `{$name}` expects " . \count($parameters) . ' argument(s)'
            );
        }

        foreach ($parameters as $index => $parameter) {
            match ($parameter) {
                ExpressionType::Value => $this->assertValue($arguments[$index], "Argument {$index} of `{$name}`"),
                ExpressionType::Logical => $this->assertLogical(
                    $arguments[$index],
                    "Argument {$index} of `{$name}`"
                ),
                ExpressionType::Nodes => $this->assertNodes($arguments[$index], "Argument {$index} of `{$name}`"),
            };
        }

        return new ExpressionNode(ExpressionNode::FUNCTION, $result, $name, $arguments);
    }

    /**
     * @return array{list<ExpressionType>, ExpressionType}
     * @throws JSONPathException
     */
    private function signature(string $name): array
    {
        return match ($name) {
            'length' => [[ExpressionType::Value], ExpressionType::Value],
            'count', 'value' => [[ExpressionType::Nodes], ExpressionType::Value],
            'match', 'search' => [
                [ExpressionType::Value, ExpressionType::Value],
                ExpressionType::Logical,
            ],
            default => throw new JSONPathException("Unknown function extension `{$name}`"),
        };
    }

    /**
     * @throws JSONPathException
     */
    private function assertValue(ExpressionNode $node, string $context): void
    {
        if ($node->type === ExpressionType::Value) {
            return;
        }

        if ($node->kind === ExpressionNode::QUERY && $node->singular) {
            return;
        }

        throw new JSONPathException("{$context} must have ValueType");
    }

    /**
     * @throws JSONPathException
     */
    private function assertLogical(ExpressionNode $node, string $context): void
    {
        if (\in_array($node->type, [ExpressionType::Logical, ExpressionType::Nodes], true)) {
            return;
        }

        throw new JSONPathException("{$context} must have LogicalType or NodesType");
    }

    /**
     * @throws JSONPathException
     */
    private function assertNodes(ExpressionNode $node, string $context): void
    {
        if ($node->type === ExpressionType::Nodes) {
            return;
        }

        throw new JSONPathException("{$context} must have NodesType");
    }

    private function isSingularQuery(string $query): bool
    {
        $length = \strlen($query);

        for ($i = 1; $i < $length;) {
            if ($query[$i] === '.') {
                if (($query[$i + 1] ?? null) === '.' || ($query[$i + 1] ?? null) === '*') {
                    return false;
                }

                $i++;
                $start = $i;

                while ($i < $length && !\in_array($query[$i], ['.', '['], true)) {
                    $i++;
                }

                if ($i === $start) {
                    return false;
                }

                continue;
            }

            if ($query[$i] !== '[') {
                return false;
            }

            $end = $this->findClosingBracket($query, $i);

            if ($end === null) {
                return false;
            }

            $selector = \trim(\substr($query, $i + 1, $end - $i - 1));

            if (
                !\preg_match('/^-?(?:0|[1-9]\d*)$/', $selector)
                && !$this->isQuotedSelector($selector)
            ) {
                return false;
            }

            $i = $end + 1;
        }

        return true;
    }

    private function findClosingBracket(string $query, int $start): ?int
    {
        $quote = null;
        $escaped = false;
        $length = \strlen($query);

        for ($i = $start + 1; $i < $length; $i++) {
            $character = $query[$i];

            if ($quote !== null) {
                if ($character === $quote && !$escaped) {
                    $quote = null;
                }

                $escaped = $character === '\\' && !$escaped;

                if ($character !== '\\') {
                    $escaped = false;
                }

                continue;
            }

            if ($character === "'" || $character === '"') {
                $quote = $character;
            } elseif ($character === ']') {
                return $i;
            }
        }

        return null;
    }

    private function isQuotedSelector(string $selector): bool
    {
        $length = \strlen($selector);

        if (
            $selector[$length - 1] !== $selector[0]
            || $length < 2
            || !\in_array($selector[0], ["'", '"'], true)
        ) {
            return false;
        }

        $escaped = false;

        for ($i = 1; $i < $length - 1; $i++) {
            $character = $selector[$i];

            if ($character === $selector[0] && !$escaped) {
                return false;
            }

            $escaped = $character === '\\' && !$escaped;

            if ($character !== '\\') {
                $escaped = false;
            }
        }

        return true;
    }

    private function current(): ExpressionToken
    {
        return $this->tokens[$this->position];
    }

    private function check(string $type, mixed $value = null): bool
    {
        $token = $this->current();

        return $token->type === $type && ($value === null || $token->value === $value);
    }

    private function match(string $type, mixed $value = null): bool
    {
        if (!$this->check($type, $value)) {
            return false;
        }

        $this->position++;

        return true;
    }

    /**
     * @throws JSONPathException
     */
    private function expect(string $type, mixed $value = null): ExpressionToken
    {
        $token = $this->current();

        if (!$this->match($type, $value)) {
            throw new JSONPathException("Unexpected token at position {$token->position}");
        }

        return $token;
    }
}
