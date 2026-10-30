<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;
use WilyCode\Database\Query\ParameterBag;

/**
 * JSON_ARRAY(...) literal expression.
 *
 * Items can be:
 *  - string SQL fragments (e.g. "u.id", "NOW()")
 *  - Expression instances (including nested JsonObject/JsonArraySubquery)
 *  - scalar values (int/bool/float/null) which will be bound as parameters
 *  - arrays (list => nested JsonArray, associative => nested JsonObject)
 *
 * Note: string values are treated as SQL fragments by default.
 * For literal strings, wrap them with ValueExpression::of('text').
 */
final class JsonArray implements Expression
{
    /** @var array<int, mixed> */
    private array $items;

    /**
     * @param array<int, mixed> $items
     */
    public function __construct(array $items)
    {
        $this->items = array_values($items);
    }

    /** @param array<int, mixed> $items */
    public static function of(array $items): self
    {
        return new self($items);
    }

    public function compile(ParameterBag $bag): string
    {
        $compiled = [];
        foreach ($this->items as $item) {
            $compiled[] = JsonValueCompiler::compileValue($item, $bag);
        }

        return $bag->dialect()->jsonArray($compiled);
    }
}
