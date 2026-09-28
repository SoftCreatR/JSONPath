<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\Filters\Expression;

final class IRegexp
{
    private const int MAX_QUANTIFIER = 10_000;

    private const string SINGLE_CHARACTER_ESCAPES = '()*+-.?[\\]^{|}nrt';

    private const string UNICODE_CATEGORIES = '(?:L[lmotu]?|M[cen]?|N[dlo]?|P[cdfios]?|Z[lps]?|S[ckmo]?|C[cfno]?)';

    public static function matches(string $value, string $expression, bool $full): bool
    {
        $compiled = self::compile($expression, $full);

        return $compiled !== null && \preg_match($compiled, $value) === 1;
    }

    private static function compile(string $expression, bool $full): ?string
    {
        if (\preg_match('//u', $expression) !== 1) {
            return null;
        }

        $position = 0;

        if (!self::parseExpression($expression, $position, false) || $position !== \strlen($expression)) {
            return null;
        }

        $translated = self::translate($expression);
        $body = $full ? '\\A(?:' . $translated . ')\\z' : '(?:' . $translated . ')';

        return '~(*LIMIT_MATCH=100000)(*LIMIT_DEPTH=1000)' . $body . '~u';
    }

    private static function parseExpression(string $expression, int &$position, bool $nested): bool
    {
        $length = \strlen($expression);

        while (true) {
            while ($position < $length && $expression[$position] !== '|' && $expression[$position] !== ')') {
                if (!self::parsePiece($expression, $position)) {
                    return false;
                }
            }

            if ($position >= $length || $expression[$position] !== '|') {
                break;
            }

            $position++;
        }

        if (!$nested) {
            return true;
        }

        if (($expression[$position] ?? null) !== ')') {
            return false;
        }

        $position++;

        return true;
    }

    private static function parsePiece(string $expression, int &$position): bool
    {
        if (!self::parseAtom($expression, $position)) {
            return false;
        }

        $quantifier = $expression[$position] ?? null;

        if ($quantifier !== null && \str_contains('*+?', $quantifier)) {
            $position++;

            return true;
        }

        if ($quantifier !== '{') {
            return true;
        }

        $end = \strpos($expression, '}', $position + 1);

        if ($end === false) {
            return false;
        }

        $range = \substr($expression, $position, $end - $position + 1);

        if (!\preg_match('/^\{(?<minimum>\d+)(?:,(?<maximum>\d*))?}$/', $range, $matches)) {
            return false;
        }

        $minimum = (int)$matches['minimum'];
        $maximum = isset($matches['maximum']) && $matches['maximum'] !== ''
            ? (int)$matches['maximum']
            : $minimum;

        if ($minimum > self::MAX_QUANTIFIER || $maximum > self::MAX_QUANTIFIER || $maximum < $minimum) {
            return false;
        }

        $position = $end + 1;

        return true;
    }

    private static function parseAtom(string $expression, int &$position): bool
    {
        $character = $expression[$position] ?? null;

        if ($character === null || \str_contains('*+?{}|)]', $character)) {
            return false;
        }

        if ($character === '(') {
            $position++;

            return self::parseExpression($expression, $position, true);
        }

        if ($character === '[') {
            return self::parseCharacterClass($expression, $position);
        }

        if ($character === '\\') {
            return self::parseEscape($expression, $position, true);
        }

        $position++;

        return true;
    }

    private static function parseCharacterClass(string $expression, int &$position): bool
    {
        $length = \strlen($expression);
        $position++;

        if (($expression[$position] ?? null) === '^') {
            $position++;
        }

        $hasElement = false;

        if (($expression[$position] ?? null) === '-') {
            $position++;
            $hasElement = true;
        }

        while ($position < $length && $expression[$position] !== ']') {
            if ($expression[$position] === '-' && ($expression[$position + 1] ?? null) === ']') {
                $position++;
                $hasElement = true;

                break;
            }

            if (!self::parseClassCharacter($expression, $position)) {
                return false;
            }

            $hasElement = true;

            if (($expression[$position] ?? null) === '-' && ($expression[$position + 1] ?? null) !== ']') {
                $position++;

                if (!self::parseClassCharacter($expression, $position)) {
                    return false;
                }
            }
        }

        if (($expression[$position] ?? null) !== ']' || !$hasElement) {
            return false;
        }

        $position++;

        return true;
    }

    private static function parseClassCharacter(string $expression, int &$position): bool
    {
        $character = $expression[$position] ?? null;

        if ($character === null || \in_array($character, ['-', '[', ']'], true)) {
            return false;
        }

        if ($character === '\\') {
            return self::parseEscape($expression, $position, true);
        }

        $position++;

        return true;
    }

    private static function parseEscape(string $expression, int &$position, bool $allowProperty): bool
    {
        $escaped = $expression[$position + 1] ?? null;

        if ($escaped === null) {
            return false;
        }

        if ($allowProperty && ($escaped === 'p' || $escaped === 'P')) {
            $remaining = \substr($expression, $position);

            if (!\preg_match('/^\\\\[pP]\{' . self::UNICODE_CATEGORIES . '}/', $remaining, $matches)) {
                return false;
            }

            $position += \strlen($matches[0]);

            return true;
        }

        if (!\str_contains(self::SINGLE_CHARACTER_ESCAPES, $escaped)) {
            return false;
        }

        $position += 2;

        return true;
    }

    private static function translate(string $expression): string
    {
        $translated = '';
        $inClass = false;
        $escaped = false;
        $length = \strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $character = $expression[$i];

            if ($escaped) {
                $translated .= $character;
                $escaped = false;

                continue;
            }

            if ($character === '\\') {
                $translated .= $character;
                $escaped = true;

                continue;
            }

            if ($character === '[') {
                $inClass = true;
            } elseif ($character === ']') {
                $inClass = false;
            }

            if (!$inClass && $character === '.') {
                $translated .= '[^\\n\\r]';
            } elseif ($character === '~') {
                $translated .= '\\~';
            } else {
                $translated .= $character;
            }
        }

        return $translated;
    }
}
