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
    public const string MATCH_INDEX = '(?!-)[\-\w]+ | \*'; // e.g.: foo or 40f35757-2563-4790-b0b1-caa904be455f

    public const string MATCH_INDEXES = '\s* -?\d+ [-?\d,\s]+'; // Eg. 0,1,2

    public const string MATCH_SLICE = '[-\d:]+ | :'; // Eg. [0:2:1]

    public const string MATCH_QUERY_RESULT = '\s* \( .+? \) \s*'; // Eg. ?(@.length - 1)

    public const string MATCH_QUERY_MATCH = '\s* \?\(.+?\) \s*'; // Eg. ?(@.foo = "bar")

    public const string MATCH_INDEX_IN_SINGLE_QUOTES = '\s* \' (.+?)? \' \s*'; // Eg. 'bar'

    public const string MATCH_INDEX_IN_DOUBLE_QUOTES = '\s* " (.+?)? " \s*'; // Eg. "bar"

    private readonly string $expression;

    private readonly int $expressionLength;

    public function __construct(string $expression)
    {
        $expression = \trim($expression);
        $len = \strlen($expression);

        if ($len === 0) {
            $this->expression = '';
            $this->expressionLength = 0;

            return;
        }

        if ($expression[0] === '$') {
            $expression = \substr($expression, 1);
        }

        if ($expression === '') {
            $this->expression = '';
            $this->expressionLength = 0;

            return;
        }

        if ($expression[0] !== '.' && $expression[0] !== '[') {
            $expression = '.' . $expression;
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

        for ($i = 0; $i < $this->expressionLength; $i++) {
            $char = $this->expression[$i];

            if (($squareBracketDepth === 0) && $char === '.') {
                if ($this->lookAhead($i) === '.') {
                    $tokens[] = new JSONPathToken(TokenType::Recursive, null);
                }

                continue;
            }

            if ($char === '[') {
                $squareBracketDepth++;

                if ($squareBracketDepth === 1) {
                    continue;
                }
            }

            if ($char === ']') {
                $squareBracketDepth--;

                if ($squareBracketDepth === 0) {
                    continue;
                }
            }

            /*
             * Within square brackets
             */
            if ($squareBracketDepth > 0) {
                $tokenValue .= $char;

                if ($squareBracketDepth === 1 && $this->lookAhead($i) === ']') {
                    $tokens[] = $this->createToken($tokenValue);
                    $tokenValue = '';
                }
            }

            /*
             * Outside square brackets
             */
            if ($squareBracketDepth === 0) {
                $tokenValue .= $char;

                if ($this->atEnd($i) || \in_array($this->lookAhead($i), ['.', '['], true)) {
                    $tokens[] = $this->createToken($tokenValue);
                    $tokenValue = '';
                }
            }
        }

        if ($tokenValue !== '') {
            $tokens[] = $this->createToken($tokenValue);
        }

        return $tokens;
    }

    protected function lookAhead(int $pos, int $forward = 1): ?string
    {
        return $this->expression[$pos + $forward] ?? null;
    }

    protected function atEnd(int $pos): bool
    {
        return $pos === $this->expressionLength;
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
    protected function createToken(string $value): JSONPathToken
    {
        // The IDE doesn't like, what we do with $value, so let's
        // move it to a separate variable, to get rid of any IDE warnings
        $tokenValue = $value;

        /** @var JSONPathToken|null $ret */
        $ret = null;

        if (\preg_match('/^(' . static::MATCH_INDEX . ')$/xu', $tokenValue, $matches)) {
            if (\preg_match('/^-?\d+$/', $tokenValue)) {
                $tokenValue = (int)$tokenValue;
            }

            $ret = new JSONPathToken(TokenType::Index, $tokenValue);
        } elseif (\preg_match('/^' . static::MATCH_INDEXES . '$/xu', $tokenValue, $matches)) {
            $tokenValue = \explode(',', \trim($tokenValue, ','));

            foreach ($tokenValue as $i => $v) {
                $tokenValue[$i] = (int)\trim($v);
            }

            $ret = new JSONPathToken(TokenType::Indexes, $tokenValue);
        } elseif (\preg_match('/^' . static::MATCH_SLICE . '$/xu', $tokenValue, $matches)) {
            $parts = \explode(':', $tokenValue);
            $tokenValue = [
                'start' => $parts[0] !== '' ? (int)$parts[0] : null,
                'end' => isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null,
                'step' => isset($parts[2]) && $parts[2] !== '' ? (int)$parts[2] : null,
            ];

            $ret = new JSONPathToken(TokenType::Slice, $tokenValue);
        } elseif (\preg_match('/^' . static::MATCH_QUERY_RESULT . '$/xu', $tokenValue)) {
            $tokenValue = \substr($tokenValue, 1, -1);

            $ret = new JSONPathToken(TokenType::QueryResult, $tokenValue);
        } elseif (\preg_match('/^' . static::MATCH_QUERY_MATCH . '$/xu', $tokenValue)) {
            $tokenValue = \substr($tokenValue, 2, -1);

            $ret = new JSONPathToken(TokenType::QueryMatch, $tokenValue);
        } elseif (
            \preg_match('/^' . static::MATCH_INDEX_IN_SINGLE_QUOTES . '$/xu', $tokenValue, $matches)
            || \preg_match('/^' . static::MATCH_INDEX_IN_DOUBLE_QUOTES . '$/xu', $tokenValue, $matches)
        ) {
            if (isset($matches[1])) {
                $tokenValue = \trim($matches[1]);

                $possibleArray = false;
                if ($matches[0][0] === '"') {
                    $possibleArray = \explode('","', $tokenValue);
                } elseif ($matches[0][0] === "'") {
                    $possibleArray = \explode("','", $tokenValue);
                }
                if ($possibleArray !== false && \count($possibleArray) > 1) {
                    $tokenValue = $possibleArray;
                }
            } else {
                $tokenValue = '';
            }

            $ret = new JSONPathToken(TokenType::Index, $tokenValue);
        }

        if ($ret !== null) {
            return $ret;
        }

        throw new JSONPathException("Unable to parse token {$tokenValue} in expression: {$this->expression}");
    }
}
