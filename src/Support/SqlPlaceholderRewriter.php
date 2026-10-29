<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Support;

/**
 * Rewrites SQL placeholders while ignoring quoted strings, identifiers and comments.
 *
 * @internal
 */
final class SqlPlaceholderRewriter
{
    private function __construct() {}

    /**
     * @param null|callable(int):string $onPositional Receives the byte offset of `?`.
     * @param null|callable(string, int):string $onNamed Receives the name without `:` and byte offset.
     */
    public static function rewrite(
        string $sql,
        ?callable $onPositional = null,
        ?callable $onNamed = null,
    ): string {
        $output = '';
        $length = strlen($sql);
        $offset = 0;

        while ($offset < $length) {
            $character = $sql[$offset];

            // PostgreSQL dollar-quoted strings contain no placeholders.
            if ($character === '$' && preg_match('/\\G(\\$(?:[a-zA-Z_][a-zA-Z0-9_]*)?\\$)/', $sql, $match, 0, $offset) === 1) {
                $delimiter = $match[1];
                $end = strpos($sql, $delimiter, $offset + strlen($delimiter));
                if ($end !== false) {
                    $end += strlen($delimiter);
                    $output .= substr($sql, $offset, $end - $offset);
                    $offset = $end;
                    continue;
                }
            }

            if ($character === "'") {
                [$fragment, $offset] = self::readQuoted($sql, $offset, "'");
                $output .= $fragment;
                continue;
            }

            if ($character === '"') {
                [$fragment, $offset] = self::readQuoted($sql, $offset, '"');
                $output .= $fragment;
                continue;
            }

            if ($character === '`') {
                [$fragment, $offset] = self::readQuoted($sql, $offset, '`', allowBackslashEscapes: false);
                $output .= $fragment;
                continue;
            }

            if ($character === '#' && ($sql[$offset + 1] ?? '') !== '>') {
                [$fragment, $offset] = self::readLineComment($sql, $offset);
                $output .= $fragment;
                continue;
            }

            if (
                $character === '-'
                && $offset + 1 < $length
                && $sql[$offset + 1] === '-'
                && ($offset + 2 >= $length || ctype_space($sql[$offset + 2]))
            ) {
                [$fragment, $offset] = self::readLineComment($sql, $offset);
                $output .= $fragment;
                continue;
            }

            if ($character === '/' && $offset + 1 < $length && $sql[$offset + 1] === '*') {
                [$fragment, $offset] = self::readBlockComment($sql, $offset);
                $output .= $fragment;
                continue;
            }

            if ($character === '?' && $onPositional !== null) {
                $output .= $onPositional($offset);
                $offset++;
                continue;
            }

            if (
                $character === ':'
                && ($offset === 0 || $sql[$offset - 1] !== ':')
                && $offset + 1 < $length
                && $sql[$offset + 1] !== ':'
                && (ctype_alpha($sql[$offset + 1]) || $sql[$offset + 1] === '_')
            ) {
                $end = $offset + 2;
                while ($end < $length && (ctype_alnum($sql[$end]) || $sql[$end] === '_')) {
                    $end++;
                }

                $name = substr($sql, $offset + 1, $end - $offset - 1);
                $output .= $onNamed !== null
                    ? $onNamed($name, $offset)
                    : ':' . $name;
                $offset = $end;
                continue;
            }

            $output .= $character;
            $offset++;
        }

        return $output;
    }

    /** @return array{0:string,1:int} */
    private static function readQuoted(
        string $sql,
        int $offset,
        string $quote,
        bool $allowBackslashEscapes = true,
    ): array {
        $length = strlen($sql);
        $start = $offset;
        $offset++;

        while ($offset < $length) {
            if ($sql[$offset] !== $quote) {
                $offset++;
                continue;
            }

            if ($offset + 1 < $length && $sql[$offset + 1] === $quote) {
                $offset += 2;
                continue;
            }

            if ($allowBackslashEscapes && self::isEscapedByBackslash($sql, $offset)) {
                $offset++;
                continue;
            }

            $offset++;
            break;
        }

        return [substr($sql, $start, $offset - $start), $offset];
    }

    /** @return array{0:string,1:int} */
    private static function readLineComment(string $sql, int $offset): array
    {
        $length = strlen($sql);
        $start = $offset;

        while ($offset < $length && $sql[$offset] !== "\n") {
            $offset++;
        }

        if ($offset < $length) {
            $offset++;
        }

        return [substr($sql, $start, $offset - $start), $offset];
    }

    /** @return array{0:string,1:int} */
    private static function readBlockComment(string $sql, int $offset): array
    {
        $length = strlen($sql);
        $start = $offset;
        $offset += 2;

        while ($offset < $length) {
            if ($sql[$offset] === '*' && $offset + 1 < $length && $sql[$offset + 1] === '/') {
                $offset += 2;
                break;
            }
            $offset++;
        }

        return [substr($sql, $start, $offset - $start), $offset];
    }

    private static function isEscapedByBackslash(string $sql, int $offset): bool
    {
        $backslashes = 0;
        for ($index = $offset - 1; $index >= 0 && $sql[$index] === '\\'; $index--) {
            $backslashes++;
        }

        return $backslashes % 2 === 1;
    }
}
