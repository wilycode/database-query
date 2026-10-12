<?php

declare(strict_types=1);

namespace WilyCode\Database\Query;

use InvalidArgumentException;
use WilyCode\Database\Query\Support\SqlPlaceholderRewriter;

final class ParameterProcessor
{
    /**
     * Converts raw positional and named parameters to canonical named placeholders.
     * Array parameters must be enclosed in parentheses, for example `IN (:ids)`.
     */
    /** @param array<int|string, mixed> $parameters */
    public static function process(
        string $sql,
        array $parameters,
        ParameterBag $bag,
        string $prefix = 'param',
    ): string {
        [$positional, $named] = self::normalizeParameters($parameters);
        $positionalIndex = 0;
        $usedNamed = [];
        $namedOccurrences = [];

        $rewritten = SqlPlaceholderRewriter::rewrite(
            $sql,
            static function (int $offset) use (
                &$positionalIndex,
                $positional,
                $bag,
                $prefix,
                $sql,
            ): string {
                if (!array_key_exists($positionalIndex, $positional)) {
                    throw new InvalidArgumentException('A positional placeholder has no matching value.');
                }

                $value = $positional[$positionalIndex++];
                if (!is_array($value)) {
                    return $bag->addAuto($value, $prefix);
                }

                return self::bindList($sql, $offset, 1, $value, $bag, $prefix);
            },
            static function (string $name, int $offset) use (
                $named,
                &$usedNamed,
                &$namedOccurrences,
                $bag,
                $sql,
            ): string {
                if (!array_key_exists($name, $named)) {
                    throw new InvalidArgumentException(sprintf(
                        'Named placeholder ":%s" has no matching value.',
                        $name,
                    ));
                }

                $usedNamed[$name] = true;
                $value = $named[$name];

                if (is_array($value)) {
                    return self::bindList(
                        $sql,
                        $offset,
                        strlen($name) + 1,
                        $value,
                        $bag,
                        $name,
                    );
                }

                $occurrence = ($namedOccurrences[$name] ?? 0) + 1;
                $namedOccurrences[$name] = $occurrence;

                if ($occurrence === 1 && !$bag->has($name)) {
                    return $bag->add($value, $name);
                }

                return $bag->addAuto($value, $name);
            },
        );

        if ($positionalIndex !== count($positional)) {
            throw new InvalidArgumentException('More positional values were provided than placeholders.');
        }

        foreach ($named as $name => $_) {
            if (!isset($usedNamed[$name])) {
                throw new InvalidArgumentException(sprintf(
                    'Parameter "%s" has no matching placeholder in the query.',
                    $name,
                ));
            }
        }

        return $rewritten;
    }

    /**
     * @param array<int|string, mixed> $parameters
     * @return array{0:list<mixed>,1:array<string,mixed>}
     */
    private static function normalizeParameters(array $parameters): array
    {
        $positional = [];
        $named = [];

        foreach ($parameters as $key => $value) {
            if (is_int($key) || ctype_digit($key)) {
                $positional[] = $value;
                continue;
            }

            $name = ltrim((string) $key, ':');
            if (preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name) !== 1) {
                throw new InvalidArgumentException(sprintf('Invalid parameter name "%s".', $name));
            }
            if (array_key_exists($name, $named)) {
                throw new InvalidArgumentException(sprintf('Duplicate parameter name "%s".', $name));
            }

            $named[$name] = $value;
        }

        return [$positional, $named];
    }

    /** @param array<array-key, mixed> $value */
    private static function bindList(
        string $sql,
        int $offset,
        int $placeholderLength,
        array $value,
        ParameterBag $bag,
        string $prefix,
    ): string {
        self::assertListPlaceholderIsParenthesized($sql, $offset, $placeholderLength);

        if ($value === []) {
            return 'SELECT 1 WHERE 0=1';
        }

        return implode(', ', $bag->addList($value, $prefix));
    }

    private static function assertListPlaceholderIsParenthesized(
        string $sql,
        int $offset,
        int $placeholderLength,
    ): void {
        $left = $offset - 1;
        while ($left >= 0 && ctype_space($sql[$left])) {
            $left--;
        }

        $right = $offset + $placeholderLength;
        $length = strlen($sql);
        while ($right < $length && ctype_space($sql[$right])) {
            $right++;
        }

        if ($left < 0 || $right >= $length || $sql[$left] !== '(' || $sql[$right] !== ')') {
            throw new InvalidArgumentException(
                'Array parameters must be used inside parentheses, for example IN (:ids).',
            );
        }
    }
}
