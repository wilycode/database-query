<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Dialect;

use WilyCode\Database\Query\Enum\ParameterType;

/** Common SQL syntax; engine-specific features remain explicit in each dialect. */
abstract class AbstractDialect implements DialectInterface
{
    public function tableAlias(string $table, string $alias): string
    {
        return $table . ' AS ' . $alias;
    }

    public function insert(string $table, array $columns, array $rows): string
    {
        $values = array_map(static fn (array $row): string => '(' . implode(', ', $row) . ')', $rows);
        return 'INSERT INTO ' . $table . ' (' . implode(', ', $columns) . ') VALUES ' . implode(', ', $values);
    }

    public function unionOperand(string $sql): string
    {
        return '(' . $sql . ')';
    }

    public function jsonParameter(string $placeholder, ParameterType $type): string
    {
        return $placeholder;
    }

    public function jsonResult(string $sql): string
    {
        return $sql;
    }
}
