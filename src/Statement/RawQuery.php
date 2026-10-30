<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\ParameterProcessor;

final class RawQuery implements QueryInterface
{
    private string $sql;
    private ParameterBag $parameters;

    /** @param array<int|string, mixed> $parameters */
    public function __construct(string $sql, array $parameters = [])
    {
        $this->parameters = new ParameterBag();
        $this->sql = ParameterProcessor::process($sql, $parameters, $this->parameters);
    }

    public function compile(?DialectInterface $dialect = null): CompiledQuery
    {
        return new CompiledQuery(
            $this->sql,
            $this->parameters->getParameters(),
            $this->parameters->getTypes(),
        );
    }

}
