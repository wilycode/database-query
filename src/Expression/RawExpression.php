<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\ParameterProcessor;

final class RawExpression implements Expression
{
    /** @param array<int|string, mixed> $parameters */
    public function __construct(
        private readonly string $sql,
        private readonly array $parameters = [],
    ) {}

    /** @param array<int|string, mixed> $parameters */
    public static function raw(string $sql, array $parameters = []): self
    {
        return new self($sql, $parameters);
    }

    public function compile(ParameterBag $bag): string
    {
        return ParameterProcessor::process(
            $this->sql,
            $this->parameters,
            $bag,
        );
    }
}
