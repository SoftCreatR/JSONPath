<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\RFC9535;

use Flow\JSONPath\Filters\Expression\ExpressionParser;
use Flow\JSONPath\JSONPathException;
use Flow\JSONPath\JSONPathToken;
use Flow\JSONPath\TokenType;

/**
 * Parses the RFC query grammar independently of document contents and legacy extensions.
 */
final class QueryParser
{
    private int $position = 1;

    /**
     * Retains the complete query for strict, eager validation.
     */
    public function __construct(private readonly string $query)
    {
    }

    /**
     * Compiles child and descendant segments into reusable selector tokens.
     *
     * @return list<JSONPathToken>
     * @throws JSONPathException
     */
    public function parse(): array
    {
        if (($this->query[0] ?? '') !== '$' || \preg_match('//u', $this->query) !== 1) {
            throw new JSONPathException('An RFC 9535 query must start with `$` and contain valid UTF-8');
        }

        $tokens = [];
        $length = \strlen($this->query);

        while ($this->position < $length) {
            $this->whitespace();
            $character = $this->query[$this->position] ?? '';

            if ($character === '.') {
                $this->position++;

                if (($this->query[$this->position] ?? '') === '.') {
                    $this->position++;
                    $tokens[] = new JSONPathToken(TokenType::Recursive, null);

                    if (($this->query[$this->position] ?? '') === '[') {
                        $tokens[] = $this->bracket();

                        continue;
                    }
                }

                $tokens[] = $this->shorthand();
            } elseif ($character === '[') {
                $tokens[] = $this->bracket();
            } else {
                throw new JSONPathException('Expected an RFC child or descendant segment');
            }
        }

        return $tokens;
    }

    /**
     * Reads an adjacent member shorthand or wildcard without accepting whitespace.
     *
     * @throws JSONPathException
     */
    private function shorthand(): JSONPathToken
    {
        if (($this->query[$this->position] ?? '') === '*') {
            $this->position++;

            return new JSONPathToken(TokenType::Index, '*');
        }

        if (!\preg_match('/\G[A-Za-z_\x{80}-\x{10ffff}][A-Za-z_0-9\x{80}-\x{10ffff}]*/u', $this->query, $matches, 0, $this->position)) {
            throw new JSONPathException('Invalid member name shorthand');
        }

        $this->position += \strlen($matches[0]);

        return new JSONPathToken(TokenType::Index, $matches[0], true);
    }

    /**
     * Reads an ordered selector list, preserving repeated selectors.
     *
     * @throws JSONPathException
     */
    private function bracket(): JSONPathToken
    {
        $this->position++;
        $selectors = [];

        do {
            $this->whitespace();
            $character = $this->query[$this->position] ?? '';

            if ($character === "'" || $character === '"') {
                $selectors[] = new JSONPathToken(TokenType::Index, StringLiteral::read($this->query, $this->position), true);
            } elseif ($character === '*') {
                $this->position++;
                $selectors[] = new JSONPathToken(TokenType::Index, '*');
            } else {
                $selectors[] = $this->selector();
            }

            $this->whitespace();
            $separator = $this->query[$this->position++] ?? '';

            if ($separator !== ',' && $separator !== ']') {
                throw new JSONPathException('Expected a comma or closing bracket');
            }
        } while ($separator === ',');

        return \count($selectors) === 1 ? $selectors[0] : new JSONPathToken(TokenType::Indexes, $selectors);
    }

    /**
     * Reads a filter, slice, or integer index up to its enclosing separator.
     *
     * @throws JSONPathException
     */
    private function selector(): JSONPathToken
    {
        $filter = ($this->query[$this->position] ?? '') === '?';
        $start = $this->position + ($filter ? 1 : 0);
        $depth = 0;
        $parentheses = 0;
        $length = \strlen($this->query);

        while ($this->position < $length) {
            $character = $this->query[$this->position];

            if ($character === "'" || $character === '"') {
                StringLiteral::read($this->query, $this->position);

                continue;
            }

            if ($character === '[') {
                $depth++;
            } elseif ($character === ']') {
                if ($depth === 0) {
                    break;
                }

                $depth--;
            } elseif ($character === '(') {
                $parentheses++;
            } elseif ($character === ')') {
                $parentheses--;
            } elseif ($character === ',' && $depth === 0 && $parentheses === 0) {
                break;
            }

            $this->position++;
        }

        $text = \substr($this->query, $start, $this->position - $start);

        if ($filter) {
            return new JSONPathToken(TokenType::QueryMatch, (new ExpressionParser($text, strict: true))->parse());
        }

        if (!\str_contains($text, ':')) {
            return new JSONPathToken(TokenType::Index, $this->integer($text));
        }

        $parts = \explode(':', $text);

        if (\count($parts) > 3) {
            throw new JSONPathException('A slice has at most three bounds');
        }

        $slice = [];

        foreach (['start', 'end', 'step'] as $index => $name) {
            $part = \trim($parts[$index] ?? '', " \t\r\n");
            $slice[$name] = $part === '' ? null : $this->integer($part);
        }

        return new JSONPathToken(TokenType::Slice, $slice);
    }

    /**
     * Enforces the RFC integer grammar and interoperable exact-integer range.
     *
     * @throws JSONPathException
     */
    private function integer(string $text): int
    {
        $text = \trim($text, " \t\r\n");
        $digits = \ltrim($text, '-');

        if (!\preg_match('/^(?:0|-?[1-9][0-9]*)$/D', $text) || \strlen($digits) > 16 || (\strlen($digits) === 16 && \strcmp($digits, '9007199254740991') > 0)) {
            throw new JSONPathException('Invalid RFC integer index or slice bound');
        }

        return (int)$text;
    }

    /**
     * Consumes only the four whitespace characters admitted by the RFC grammar.
     */
    private function whitespace(): void
    {
        $this->position += \strspn($this->query, " \t\r\n", $this->position);
    }
}
