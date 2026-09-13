<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath;

class JSONPathLexer
{
    /*
     * Match within bracket groups
     * Matches are whitespace insensitive
     */

    // e.g.: foo or 40f35757-2563-4790-b0b1-caa904be455f or $
    public const string MATCH_INDEX = '(?!-)[\-\w]+ | \\$ | \\*';

    // Eg. [0:2:1] or [-1]
    public const string MATCH_SLICE = '(?:-?\d*:-?\d*(?::-?\d*)?|-\\d+)';

    // Eg. ?(@.foo = "bar")
    public const string MATCH_QUERY_MATCH = '\s* \?\(.+?\) \s*';

    // Eg. 'bar'
    public const string MATCH_INDEX_IN_SINGLE_QUOTES = '\s* \' (.+?)? \' \s*';

    // Eg. "bar"
    public const string MATCH_INDEX_IN_DOUBLE_QUOTES = '\s* " (.+?)? " \s*';

    private readonly string $expression;

    private readonly int $expressionLength;

    /**
     * @throws JSONPathException
     */
    public function __construct(string $expression)
    {
        $expression = \trim($expression);

        if ($expression === '') {
            throw new JSONPathException('A JSONPath query must start with the root identifier `$`');
        }

        if ($expression[0] === '$') {
            $expression = \substr($expression, 1);
        } elseif (\preg_match('/^(?!-)[\-\w]+$/u', $expression)) {
            $expression = '.' . $expression;
        } else {
            throw new JSONPathException('A JSONPath query must start with the root identifier `$`');
        }

        if ($expression === '') {
            $this->expression = '';
            $this->expressionLength = 0;

            return;
        }

        if ($expression[0] !== '.' && $expression[0] !== '[') {
            throw new JSONPathException('A JSONPath segment must start with `.` or `[`');
        }

        $this->expression = $expression;
        $this->expressionLength = \strlen($expression);
    }

    /**
     * @return list<JSONPathToken>
     * @throws JSONPathException
     */
    public function parseExpressionTokens(): array
    {
        $squareBracketDepth = 0;
        $tokenValue = '';
        $tokens = [];
        $inBracketQuote = null;

        for ($i = 0; $i < $this->expressionLength; $i++) {
            $char = $this->expression[$i];

            if ($squareBracketDepth === 0 && ($char === "'" || $char === '"')) {
                throw new JSONPathException('Quoted member names require bracket notation');
            }

            if ($squareBracketDepth === 0 && $char === '.') {
                if ($this->lookAhead($i) === '.') {
                    if (\in_array($this->lookAhead($i, 2), [null, '.', "'", '"'], true)) {
                        throw new JSONPathException('Recursive descent requires a selector');
                    }

                    $tokens[] = new JSONPathToken(TokenType::Recursive, null);
                    $i++;
                } elseif (\in_array($this->lookAhead($i), [null, '[', "'", '"', '$'], true)) {
                    throw new JSONPathException('Dot notation requires a member name or wildcard');
                }

                continue;
            }

            if ($char === '[' && $inBracketQuote === null) {
                $squareBracketDepth++;

                if ($squareBracketDepth === 1) {
                    $inBracketQuote = null;

                    continue;
                }
            }

            if ($char === ']' && $squareBracketDepth > 0 && $inBracketQuote === null) {
                $squareBracketDepth--;

                if ($squareBracketDepth === 0) {
                    $tokens[] = $this->createToken($tokenValue, true);
                    $tokenValue = '';

                    continue;
                }
            }

            /*
             * Within square brackets
             */
            if ($squareBracketDepth > 0) {
                if (($char === "'" || $char === '"')) {
                    $escaped = $this->isEscaped($tokenValue);

                    if ($inBracketQuote === null && !$escaped) {
                        $inBracketQuote = $char;
                    } elseif ($inBracketQuote === $char && !$escaped) {
                        $inBracketQuote = null;
                    }
                }

                $tokenValue .= $char;

                continue;
            }

            /*
             * Outside square brackets
             */
            $tokenValue .= $char;

            if ($this->atEnd($i) || \in_array($this->lookAhead($i), ['.', '['], true)) {
                $tokens[] = $this->createToken($tokenValue, false);
                $tokenValue = '';
            }
        }

        if ($tokenValue !== '') {
            $tokens[] = $this->createToken($tokenValue, $squareBracketDepth > 0);
        }

        return $tokens;
    }

    protected function lookAhead(int $pos, int $forward = 1): ?string
    {
        return $this->expression[$pos + $forward] ?? null;
    }

    protected function atEnd(int $pos): bool
    {
        return $pos === ($this->expressionLength - 1);
    }

    /**
     * @return list<JSONPathToken>
     * @throws JSONPathException
     */
    public function parseExpression(): array
    {
        return $this->parseExpressionTokens();
    }

    /**
     * @throws JSONPathException
     */
    protected function createToken(string $value, bool $bracketed): JSONPathToken
    {
        // The IDE doesn't like, what we do with $value, so let's
        // move it to a separate variable, to get rid of any IDE warnings
        $tokenValue = \trim($value);

        if (!$bracketed && $tokenValue !== $value) {
            throw new JSONPathException('Whitespace is not allowed in dot notation');
        }

        /** @var JSONPathToken|null $ret */
        $ret = null;

        $quotedIndex = $this->decodeCompleteQuotedIndex($tokenValue);

        if ($quotedIndex !== null) {
            return new JSONPathToken(TokenType::Index, $quotedIndex, true, bracketed: $bracketed);
        }

        if ($bracketed && \str_contains($tokenValue, ',')) {
            $parts = \array_values(\array_filter(
                \array_map('trim', \explode(',', $tokenValue)),
                static fn (string $part): bool => $part !== ''
            ));

            if ($parts !== []) {
                $union = [];

                $hasSlice = false;
                $hasQuery = false;

                foreach ($parts as $part) {
                    $quotedPart = $this->decodeCompleteQuotedIndex($part);

                    if ($quotedPart !== null) {
                        $union[] = $quotedPart;

                        continue;
                    }

                    if (\preg_match('/^-\\d+$/', $part)) {
                        $union[] = (int)$part;

                        continue;
                    }

                    if (\preg_match('/^' . static::MATCH_SLICE . '$/u', $part)) {
                        $union[] = [
                            'type' => 'slice',
                            'value' => $this->parseSlice($part),
                        ];
                        $hasSlice = true;

                        continue;
                    }

                    if ($part === '*' || \preg_match('/^\d+$/', $part)) {
                        $union[] = \preg_match('/^\d+$/', $part) ? (int)$part : $part;

                        continue;
                    }

                    if (\preg_match('/^' . static::MATCH_QUERY_MATCH . '$/xu', $part)) {
                        $union[] = [
                            'type' => 'query',
                            'value' => \substr($part, 2, -1),
                        ];
                        $hasQuery = true;
                    }
                }

                if (\count($union) === \count($parts)) {
                    $quotedPattern = '/^(' . static::MATCH_INDEX_IN_SINGLE_QUOTES . '|'
                        . static::MATCH_INDEX_IN_DOUBLE_QUOTES . ')$/xu';

                    $quotedCallback = static function (string $part) use ($quotedPattern): bool {
                        return \preg_match($quotedPattern, $part) === 1;
                    };

                    $quotedParts = \array_filter($parts, $quotedCallback);

                    $allQuoted = \count($quotedParts) === \count($parts);

                    if ($hasQuery || (\in_array('*', $union, true) && \count($union) > 1)) {
                        throw new JSONPathException('Unsupported selector union');
                    }

                    $tokenType = ($hasSlice || !$allQuoted) ? TokenType::Indexes : TokenType::Index;

                    return new JSONPathToken($tokenType, $union, $allQuoted, bracketed: true);
                }
            }
        }

        if (\preg_match('/^-\\d+$/', $tokenValue)) {
            return new JSONPathToken(TokenType::Index, (int)$tokenValue, bracketed: $bracketed);
        }

        if ($tokenValue === '') {
            throw new JSONPathException('Empty selectors are not supported');
        }

        if (
            ($tokenValue[0] === "'" || $tokenValue[0] === '"')
            && $tokenValue[\strlen($tokenValue) - 1] === $tokenValue[0]
        ) {
            throw new JSONPathException('Quoted member names must be atomic');
        }

        if (\preg_match('/^(' . static::MATCH_INDEX . ')$/xu', $tokenValue, $matches)) {
            if ($bracketed && $tokenValue !== '*' && !\preg_match('/^\d+$/', $tokenValue)) {
                throw new JSONPathException('Member names in bracket notation must be quoted');
            }

            if (\preg_match('/^-?\d+$/', $tokenValue)) {
                $tokenValue = (int)$tokenValue;
            }

            $ret = new JSONPathToken(TokenType::Index, $tokenValue, bracketed: $bracketed);
        } elseif (\preg_match('/^' . static::MATCH_SLICE . '$/xu', $tokenValue, $matches)) {
            $tokenValue = $this->parseSlice($tokenValue);

            $ret = new JSONPathToken(TokenType::Slice, $tokenValue, bracketed: true);
        } elseif ($tokenValue === '?()') {
            throw new JSONPathException('Filter expressions must not be empty');
        } elseif ($tokenValue === '?') {
            throw new JSONPathException('Filter expressions must not be empty');
        } elseif (\preg_match('/^\\?@/', $tokenValue)) {
            $expr = \substr($tokenValue, 1);
            $expr = $expr === '' ? '@' : $expr;

            $ret = new JSONPathToken(TokenType::QueryMatch, $expr, shorthand: true, bracketed: true);
        } elseif (\preg_match('/^' . static::MATCH_QUERY_MATCH . '$/xu', $tokenValue)) {
            $tokenValue = \substr($tokenValue, 2, -1);

            $ret = new JSONPathToken(TokenType::QueryMatch, $tokenValue, bracketed: true);
        }

        if ($ret !== null) {
            return $ret;
        }

        throw new JSONPathException("Unable to parse token {$tokenValue} in expression: {$this->expression}");
    }

    /**
     * @return array{start: int|null, end: int|null, step: int|null}
     */
    private function parseSlice(string $tokenValue): array
    {
        $parts = \explode(':', $tokenValue);

        return [
            'start' => $parts[0] !== '' ? (int)$parts[0] : null,
            'end' => isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null,
            'step' => isset($parts[2]) && $parts[2] !== '' ? (int)$parts[2] : null,
        ];
    }

    private function isEscaped(string $tokenValue): bool
    {
        $len = \strlen($tokenValue);
        if ($len === 0) {
            return false;
        }

        $backslashCount = 0;

        for ($i = $len - 1; $i >= 0; $i--) {
            if ($tokenValue[$i] === '\\') {
                $backslashCount++;
                continue;
            }

            break;
        }

        return ($backslashCount % 2) === 1;
    }

    private function decodeQuotedIndex(string $tokenValue, string $quote): string
    {
        // Unescape backslashes first, then the quote type used
        $tokenValue = \str_replace('\\\\', '\\', $tokenValue);

        if ($quote === "'") {
            $tokenValue = \str_replace("\\'", "'", $tokenValue);
        } elseif ($quote === '"') {
            $tokenValue = \str_replace('\\"', '"', $tokenValue);
        }

        return $tokenValue;
    }

    private function decodeCompleteQuotedIndex(string $tokenValue): ?string
    {
        $length = \strlen($tokenValue);

        if ($length < 2 || !\in_array($tokenValue[0], ["'", '"'], true)) {
            return null;
        }

        $quote = $tokenValue[0];

        if ($tokenValue[$length - 1] !== $quote) {
            return null;
        }

        $contents = '';

        for ($i = 1; $i < $length - 1; $i++) {
            $char = $tokenValue[$i];

            if ($char === $quote && !$this->isEscaped($contents)) {
                return null;
            }

            $contents .= $char;
        }

        return $this->decodeQuotedIndex($contents, $quote);
    }
}
