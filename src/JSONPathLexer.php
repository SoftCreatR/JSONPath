<?php
/**
 * JSONPath implementation for PHP.
 *
 * @copyright Copyright (c) 2018 Flow Communications
 * @license   MIT <https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE>
 */
declare(strict_types=1);

namespace Flow\JSONPath;

use function explode;
use function in_array;
use function preg_match;
use function strlen;
use function substr;
use function trim;

class JSONPathLexer
{
    /*
     * Match within bracket groups
     * Matches are whitespace insensitive
     */
    public const MATCH_INDEX = '(?!\-)[\-\w]+ | \*'; // Eg. foo or 40f35757-2563-4790-b0b1-caa904be455f
    public const MATCH_INDEXES = '\s* -?\d+ [-?\d,\s]+'; // Eg. 0,1,2
    public const MATCH_SLICE = '[-\d:]+ | :'; // Eg. [0:2:1]
    public const MATCH_QUERY_RESULT = '\s* \( .+? \) \s*'; // Eg. ?(@.length - 1)
    public const MATCH_QUERY_MATCH = '\s* \?\(.+?\) \s*'; // Eg. ?(@.foo = "bar")
    public const MATCH_INDEX_IN_SINGLE_QUOTES = '\s* \' (.+?) \' \s*'; // Eg. 'bar'
    public const MATCH_INDEX_IN_DOUBLE_QUOTES = '\s* " (.+?) " \s*'; // Eg. 'bar'

    /**
     * The expression being lexed.
     *
     * @var string
     */
    protected $expression = '';

    /**
     * The length of the expression.
     *
     * @var int
     */
    protected $expressionLength = 0;

    /**
     * JSONPathLexer constructor.
     *
     * @param $expression
     */
    public function __construct($expression)
    {
        $expression = trim($expression);
        $len = strlen($expression);

        if (!$len) {
            return;
        }

        if ($expression[0] === '$') {
            if ($len === 1) {
                return;
            }

            $expression = substr($expression, 1);
            $len--;
        }

        if ($expression[0] !== '.' && $expression[0] !== '[') {
            $expression = '.' . $expression;
            $len++;
        }

        $this->expression = $expression;
        $this->expressionLength = $len;
    }

    /**
     * @return array
     * @throws JSONPathException
     */
    public function parseExpressionTokens(): array
    {
        $dotIndexDepth = 0;
        $squareBracketDepth = 0;
        $tokenValue = '';
        $tokens = [];

        for ($i = 0; $i < $this->expressionLength; $i++) {
            $char = $this->expression[$i];

            if (($squareBracketDepth === 0) && $char === '.') {

                if ($this->lookAhead($i, 1) === '.') {
                    $tokens[] = new JSONPathToken(JSONPathToken::T_RECURSIVE, null);
                }

                continue;
            }

            if ($char === '[') {
                ++$squareBracketDepth;

                if ($squareBracketDepth === 1) {
                    continue;
                }
            }

            if ($char === ']') {
                --$squareBracketDepth;

                if ($squareBracketDepth === 0) {
                    continue;
                }
            }

            /*
             * Within square brackets
             */
            if ($squareBracketDepth > 0) {
                $tokenValue .= $char;

                if ($squareBracketDepth === 1 && $this->lookAhead($i, 1) === ']') {
                    $tokens[] = $this->createToken($tokenValue);
                    $tokenValue = '';
                }
            }

            /*
             * Outside square brackets
             */
            if ($squareBracketDepth === 0) {
                $tokenValue .= $char;

                // Double dot ".."
                if ($char === '.' && $dotIndexDepth > 1) {
                    $tokens[] = $this->createToken($tokenValue);
                    $tokenValue = '';
                    continue;
                }

                if ($this->atEnd($i) || in_array($this->lookAhead($i, 1), ['.', '['])) {
                    $tokens[] = $this->createToken($tokenValue);
                    $tokenValue = '';
                    --$dotIndexDepth;
                }
            }
        }

        if ($tokenValue !== '') {
            $tokens[] = $this->createToken($tokenValue);
        }

        return $tokens;
    }

    /**
     * @param $pos
     * @param int $forward
     * @return string|null
     */
    protected function lookAhead($pos, $forward = 1): ?string
    {
        return $this->expression[$pos + $forward] ?? null;
    }

    /**
     * @param $pos
     * @return bool
     */
    protected function atEnd($pos): bool
    {
        return $pos === $this->expressionLength;
    }

    /**
     * @return array
     * @throws JSONPathException
     */
    public function parseExpression(): array
    {
        return $this->parseExpressionTokens();
    }

    /**
     * @param $value
     * @return string
     * @throws JSONPathException
     */
    protected function createToken($value)
    {
        if (preg_match('/^(' . static::MATCH_INDEX . ')$/xu', $value, $matches)) {
            if (preg_match('/^-?\d+$/', $value)) {
                $value = (int)$value;
            }

            return new JSONPathToken(JSONPathToken::T_INDEX, $value);
        }

        if (preg_match('/^' . static::MATCH_INDEXES . '$/xu', $value, $matches)) {
            $value = explode(',', trim($value, ','));

            foreach ($value as $i => $v) {
                $value[$i] = (int)trim($v);
            }

            return new JSONPathToken(JSONPathToken::T_INDEXES, $value);
        }

        if (preg_match('/^' . static::MATCH_SLICE . '$/xu', $value, $matches)) {
            $parts = explode(':', $value);
            $value = [
                'start' => isset($parts[0]) && $parts[0] !== '' ? (int)$parts[0] : null,
                'end' => isset($parts[1]) && $parts[1] !== '' ? (int)$parts[1] : null,
                'step' => isset($parts[2]) && $parts[2] !== '' ? (int)$parts[2] : null,
            ];

            return new JSONPathToken(JSONPathToken::T_SLICE, $value);
        }

        if (preg_match('/^' . static::MATCH_QUERY_RESULT . '$/xu', $value)) {
            $value = substr($value, 1, -1);

            return new JSONPathToken(JSONPathToken::T_QUERY_RESULT, $value);
        }

        if (preg_match('/^' . static::MATCH_QUERY_MATCH . '$/xu', $value)) {
            $value = substr($value, 2, -1);

            return new JSONPathToken(JSONPathToken::T_QUERY_MATCH, $value);
        }

        if (preg_match('/^' . static::MATCH_INDEX_IN_SINGLE_QUOTES . '$/xu', $value, $matches)) {
            $value = $matches[1];
            $value = trim($value);

            return new JSONPathToken(JSONPathToken::T_INDEX, $value);
        }

        if (preg_match('/^' . static::MATCH_INDEX_IN_DOUBLE_QUOTES . '$/xu', $value, $matches)) {
            $value = $matches[1];
            $value = trim($value);

            return new JSONPathToken(JSONPathToken::T_INDEX, $value);
        }

        throw new JSONPathException("Unable to parse token {$value} in expression: $this->expression");
    }
}
