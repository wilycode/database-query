<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\Clause\WhereClause;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\Query;

final class Delete extends WhereClause implements QueryInterface
{
    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
        parent::__construct();
    }
    public function compile(?DialectInterface $dialect = null): CompiledQuery
    {
        $bag = $this->parameters->withDialect($dialect ?? Query::dialectDefault());
        $sql = $this->buildSql($bag);

        return new CompiledQuery(
            $sql,
            $bag->getParameters(),
            $bag->getTypes(),
        );
    }

    private function buildSql(ParameterBag $bag): string
    {
        if (empty($this->table)) {
            throw new InvalidArgumentException('No table specified for DELETE');
        }

        $parts = ['DELETE FROM ' . $this->table];

        if ($where = $this->renderWheres($bag)) {
            $parts[] = 'WHERE ' . $where;
        }

        return implode(' ', $parts);
    }

}
