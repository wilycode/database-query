<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\Clause\WhereClause;
use WilyCode\Database\Query\CompiledQuery;

final class Delete extends WhereClause implements QueryInterface
{
    private string $table;

    public function __construct(string $table)
    {
        $this->table = $table;
        parent::__construct();
    }
    public function compile(): CompiledQuery
    {
        $sql = $this->buildSql();

        return new CompiledQuery(
            $sql,
            $this->parameters->getParameters(),
            $this->parameters->getTypes(),
        );
    }

    private function buildSql(): string
    {
        if (empty($this->table)) {
            throw new InvalidArgumentException('No table specified for DELETE');
        }

        $parts = ['DELETE FROM ' . $this->table];

        if ($where = $this->renderWheres()) {
            $parts[] = 'WHERE ' . $where;
        }

        return implode(' ', $parts);
    }

}
