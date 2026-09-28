<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

use Flow\JSONPath\JSONPathException;
use JsonException;

use const JSON_THROW_ON_ERROR;

final class ExpressionLexer
{
    private int $position = 0;

    private readonly int $length;

    public function __construct(private readonly string $expression)
    {
        $this->length = \strlen($expression);
    }

    /**
     * @return list<ExpressionToken>
     * @throws JSONPathException
     */
    public function tokenize(): array
    {
        $tokens = [];

        while ($this->position < $this->length) {
            $character = $this->expression[$this->position];

            if (\ctype_space($character)) {
                $this->position++;

                continue;
            }

            $tokens[] = match (true) {
                $character === '(' => $this->singleCharacterToken(ExpressionToken::LEFT_PARENTHESIS),
                $character === ')' => $this->singleCharacterToken(ExpressionToken::RIGHT_PARENTHESIS),
                $character === ',' => $this->singleCharacterToken(ExpressionToken::COMMA),
                $character === "'" || $character === '"' => $this->readString(),
                $character === '@' || $character === '$' => $this->readQuery(),
                \ctype_digit($character)
                    || (
                        $character === '-'
                        && \ctype_digit($this->peek() ?? '')
                    ) => $this->readNumber(),
                \ctype_lower($character) => $this->readIdentifier(),
                default => $this->readOperator(),
            };
        }

        $tokens[] = new ExpressionToken(ExpressionToken::END, null, $this->position);

        return $tokens;
    }

    private function singleCharacterToken(string $type): ExpressionToken
    {
        $position = $this->position++;

        return new ExpressionToken($type, $this->expression[$position], $position);
    }

    /**
     * @throws JSONPathException
     */
    private function readString(): ExpressionToken
    {
        $position = $this->position;
        $quote = $this->expression[$this->position++];
        $raw = $quote;
        $escaped = false;

        while ($this->position < $this->length) {
            $character = $this->expression[$this->position++];
            $raw .= $character;

            if ($character === $quote && !$escaped) {
                return new ExpressionToken(
                    ExpressionToken::LITERAL,
                    $this->decodeString($raw, $quote),
                    $position,
                );
            }

            if ($character === '\\') {
                $escaped = !$escaped;
            } else {
                $escaped = false;
            }
        }

        throw new JSONPathException("Unterminated string literal at position {$position}");
    }

    /**
     * @throws JSONPathException
     */
    private function decodeString(string $raw, string $quote): string
    {
        if ($quote === "'") {
            $content = \substr($raw, 1, -1);
            $jsonContent = '';
            $length = \strlen($content);

            for ($i = 0; $i < $length; $i++) {
                $character = $content[$i];

                if ($character === '\\' && isset($content[$i + 1])) {
                    $next = $content[++$i];

                    if ($next === "'") {
                        $jsonContent .= "'";
                    } elseif ($next === '"') {
                        $jsonContent .= '\\"';
                    } else {
                        $jsonContent .= '\\' . $next;
                    }

                    continue;
                }

                $jsonContent .= $character === '"' ? '\\"' : $character;
            }

            $raw = '"' . $jsonContent . '"';
        }

        try {
            $decoded = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new JSONPathException('Invalid string literal', previous: $exception);
        }

        /** @var string $decoded */
        return $decoded;
    }

    private function readQuery(): ExpressionToken
    {
        $position = $this->position;
        $bracketDepth = 0;
        $quote = null;
        $escaped = false;

        while ($this->position < $this->length) {
            $character = $this->expression[$this->position];

            if ($quote !== null) {
                $this->position++;

                if ($character === $quote && !$escaped) {
                    $quote = null;
                }

                $escaped = $character === '\\' && !$escaped;

                if ($character !== '\\') {
                    $escaped = false;
                }

                continue;
            }

            if ($bracketDepth > 0 && ($character === "'" || $character === '"')) {
                $quote = $character;
                $this->position++;

                continue;
            }

            if ($character === '[') {
                $bracketDepth++;
                $this->position++;

                continue;
            }

            if ($character === ']' && $bracketDepth > 0) {
                $bracketDepth--;
                $this->position++;

                continue;
            }

            if ($bracketDepth === 0 && \ctype_space($character)) {
                $nextPosition = $this->position;

                while ($nextPosition < $this->length && \ctype_space($this->expression[$nextPosition])) {
                    $nextPosition++;
                }

                if (\in_array($this->expression[$nextPosition] ?? null, ['.', '['], true)) {
                    $this->position = $nextPosition;

                    continue;
                }

                break;
            }

            if ($bracketDepth === 0 && $this->isQueryTerminator($character)) {
                break;
            }

            $this->position++;
        }

        return new ExpressionToken(
            ExpressionToken::QUERY,
            $this->normalizeQueryWhitespace(
                \substr($this->expression, $position, $this->position - $position)
            ),
            $position,
        );
    }

    private function isQueryTerminator(string $character): bool
    {
        if ($character === ',' || $character === ')') {
            return true;
        }

        return \str_contains('<>=!&|', $character);
    }

    private function normalizeQueryWhitespace(string $query): string
    {
        $normalized = '';
        $quote = null;
        $escaped = false;
        $length = \strlen($query);

        for ($i = 0; $i < $length; $i++) {
            $character = $query[$i];

            if ($quote !== null) {
                $normalized .= $character;

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
                $normalized .= $character;
            } elseif (!\ctype_space($character)) {
                $normalized .= $character;
            }
        }

        return $normalized;
    }

    /**
     * @throws JSONPathException
     */
    private function readNumber(): ExpressionToken
    {
        $position = $this->position;
        $remaining = \substr($this->expression, $position);

        \preg_match('/^-?(?:0|[1-9]\d*)(?:\.\d+)?(?:[eE][+-]?\d+)?/', $remaining, $matches);
        $raw = $matches[0];
        $this->position += \strlen($raw);

        try {
            $value = \json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new JSONPathException("Invalid JSON");
        }

        if (!\is_int($value) && (!\is_float($value) || !\is_finite($value))) {
            throw new JSONPathException("Invalid number at position {$position}");
        }

        return new ExpressionToken(ExpressionToken::LITERAL, $value, $position);
    }

    private function readIdentifier(): ExpressionToken
    {
        $position = $this->position;

        while (
            $this->position < $this->length
            && \preg_match('/[a-z0-9_]/', $this->expression[$this->position])
        ) {
            $this->position++;
        }

        $identifier = \substr($this->expression, $position, $this->position - $position);

        return match ($identifier) {
            'true' => new ExpressionToken(ExpressionToken::LITERAL, true, $position),
            'false' => new ExpressionToken(ExpressionToken::LITERAL, false, $position),
            'null' => new ExpressionToken(ExpressionToken::LITERAL, null, $position),
            default => new ExpressionToken(ExpressionToken::IDENTIFIER, $identifier, $position),
        };
    }

    /**
     * @throws JSONPathException
     */
    private function readOperator(): ExpressionToken
    {
        $position = $this->position;
        $twoCharacters = \substr($this->expression, $position, 2);

        if (\in_array($twoCharacters, ['==', '!=', '<=', '>=', '&&', '||'], true)) {
            $this->position += 2;

            return new ExpressionToken(ExpressionToken::OPERATOR, $twoCharacters, $position);
        }

        $character = $this->expression[$this->position];

        if (\str_contains('<>!', $character)) {
            $this->position++;

            return new ExpressionToken(ExpressionToken::OPERATOR, $character, $position);
        }

        throw new JSONPathException("Unexpected character at position {$position}");
    }

    private function peek(): ?string
    {
        return $this->expression[$this->position + 1] ?? null;
    }
}
