<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use WilyCode\Database\Query\ParameterBag;

/**
 * Represents a bound value used inside other expressions.
 *
 * This is useful when an Expression expects a SQL fragment (like JsonObject values),
 * but you want to pass a literal value (including strings) safely via parameters.
 */
final class ValueExpression implements Expression
{
    public function __construct(private readonly mixed $value) {}

    public static function of(mixed $value): self
    {
        return new self($value);
    }

    public function compileJson(ParameterBag $bag): string
    {
        $placeholder = $this->compile($bag);
        return $bag->dialect()->jsonParameter($placeholder, $bag->getTypes()[$placeholder]);
    }

    public function compile(ParameterBag $bag): string
    {
        return $bag->add($this->value);
    }
}
