<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use InvalidArgumentException;
use WilyCode\Database\Query\ParameterBag;

/**
 * JSON_OBJECT(...) expression.
 *
 * Fields are provided as associative array: jsonKey => value.
 * Values can be nested JsonObject/JsonArray/JsonArraySubquery expressions,
 * other Expression instances, scalar values (int/bool/float/null), or arrays
 * (list => JSON_ARRAY, associative => JSON_OBJECT).
 *
 * Note: string values are treated as SQL fragments/columns by default.
 * For literal strings, wrap them with ValueExpression::of('text').
 */
final class JsonObject implements Expression
{
    /** @var array<string, mixed> */
    private array $fields;

    /**
     * @param array<string, mixed> $fields
     * @param string|null $nullGuardColumn If provided, returns NULL when this column is NULL.
     *                                   Useful for LEFT JOIN relations (avoid {"id":null,...}).
     */
    public function __construct(array $fields, private readonly ?string $nullGuardColumn = null)
    {
        $this->fields = $fields;
    }

    /** @param array<string, mixed> $fields */
    public static function of(array $fields, ?string $nullGuardColumn = null): self
    {
        return new self($fields, $nullGuardColumn);
    }

    public function compile(ParameterBag $bag): string
    {
        if (empty($this->fields)) {
            // All supported dialects can construct an empty object.
            $obj = $bag->dialect()->jsonObject([]);
            return $this->nullGuardColumn ? $bag->dialect()->nullGuard($this->nullGuardColumn, $obj) : $obj;
        }

        $parts = [];
        foreach ($this->fields as $key => $value) {
            if ($key === '') {
                throw new InvalidArgumentException('JsonObject field keys must be non-empty strings.');
            }

            // Simple keys are safe literals; other keys are bound so backslashes,
            // quotes and connection string-literal modes cannot change their meaning.
            $key = (string) $key;
            $parts[] = preg_match('/^[a-zA-Z0-9_]+$/D', $key) === 1
                ? "'" . $key . "'"
                : ValueExpression::of($key)->compileJson($bag);
            $parts[] = JsonValueCompiler::compileValue($value, $bag);
        }

        $obj = $bag->dialect()->jsonObject($parts);
        return $this->nullGuardColumn ? $bag->dialect()->nullGuard($this->nullGuardColumn, $obj) : $obj;
    }

}
