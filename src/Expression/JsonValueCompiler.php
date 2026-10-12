<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use InvalidArgumentException;
use WilyCode\Database\Query\ParameterBag;

/**
 * Internal helper used by JsonObject/JsonArray to compile nested values.
 */
final class JsonValueCompiler
{
    private function __construct() {}

    /**
     * Compile a value used inside JSON expressions.
     *
     * Supported types:
     *  - Expression => compiled as-is
     *  - string => treated as SQL fragment
     *  - null => NULL
     *  - int/bool/float => bound as parameter
     *  - array => list => JsonArray, associative => JsonObject
     */
    public static function compileValue(mixed $value, ParameterBag $bag): string
    {
        if ($value instanceof Expression) {
            return $value->compile($bag);
        }

        if (is_string($value)) {
            // By default, string means SQL fragment/column. Use ValueExpression for literal strings.
            return $value;
        }

        if ($value === null) {
            return 'NULL';
        }

        if (is_int($value) || is_bool($value) || is_float($value)) {
            return $bag->add($value);
        }

        if (is_array($value)) {
            if (self::isList($value)) {
                return new JsonArray($value)->compile($bag);
            }
            return new JsonObject($value)->compile($bag);
        }

        throw new InvalidArgumentException('Unsupported JSON value type: ' . gettype($value));
    }

    /** @param array<array-key, mixed> $arr */
    private static function isList(array $arr): bool
    {
        $i = 0;
        foreach ($arr as $k => $_) {
            if ($k !== $i) {
                return false;
            }
            $i++;
        }
        return true;
    }
}
