<?php

/**
 * JSONPath implementation for PHP.
 *
 * @license https://github.com/SoftCreatR/JSONPath/blob/main/LICENSE  MIT License
 */

declare(strict_types=1);

namespace Flow\JSONPath\RFC9535;

use Flow\JSONPath\JSONPathException;
use JsonException;

/**
 * Decodes RFC string literals without treating quoted query characters as syntax.
 */
final class StringLiteral
{
    /**
     * Reads one quoted string and advances past its closing quote.
     *
     * @throws JSONPathException
     */
    public static function read(string $input, int &$position): string
    {
        $quote = $input[$position++];
        $json = '"';
        $length = \strlen($input);

        while ($position < $length) {
            $character = $input[$position++];

            if ($character === $quote) {
                try {
                    /** @var string $decoded */
                    $decoded = \json_decode($json . '"', flags: \JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new JSONPathException('Invalid string literal', previous: $exception);
                }

                return $decoded;
            }

            if ($character === '\\') {
                if ($position === $length) {
                    throw new JSONPathException('Unterminated string escape');
                }

                $escape = $input[$position++];

                if ($escape === $quote && $quote === "'") {
                    $json .= "'";
                } elseif (\str_contains('\\/bfnrtu', $escape) || ($escape === '"' && $quote === '"')) {
                    $json .= '\\' . $escape;
                } else {
                    throw new JSONPathException('Invalid string escape');
                }
            } else {
                $json .= $character === '"' ? '\\"' : $character;
            }
        }

        throw new JSONPathException('Unterminated string literal');
    }

    /**
     * Encodes one object member for a normalized path.
     */
    public static function normalized(string $name): string
    {
        $escaped = \strtr($name, ['\\' => '\\\\', "'" => "\\'", "\x08" => '\\b', "\f" => '\\f', "\n" => '\\n', "\r" => '\\r', "\t" => '\\t']);
        $escaped = \preg_replace_callback('/[\x00-\x1f]/', static fn (array $match): string => \sprintf('\\u%04x', \ord($match[0])), $escaped);

        return "['" . $escaped . "']";
    }
}
