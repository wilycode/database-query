<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Expression;

use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\Statement\QueryInterface;
use WilyCode\Database\Query\Support\ParameterImporter;

final class Subquery implements Expression
{
    public function __construct(private readonly QueryInterface $query) {}

    public function compile(ParameterBag $bag): string
    {
        $sql = ParameterImporter::import($this->query->compile(), $bag);

        return '(' . $sql . ')';
    }
}
