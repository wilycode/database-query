<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Dialect;

use WilyCode\Database\Query\Enum\ParameterType;

final class PostgreSqlDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'pgsql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function paginate(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
    {
        return $sql . ($limit !== null ? ' LIMIT ' . $limit : '')
            . ($offset !== null ? ' OFFSET ' . $offset : '');
    }

    public function jsonObject(array $parts): string
    {
        return 'JSONB_BUILD_OBJECT(' . implode(', ', $parts) . ')';
    }

    public function jsonArray(array $items): string
    {
        return 'JSONB_BUILD_ARRAY(' . implode(', ', $items) . ')';
    }

    public function jsonAggregate(string $item, bool $distinct, bool $coalesce): string
    {
        $sql = 'JSONB_AGG(' . ($distinct ? 'DISTINCT ' : '') . $item . ')';
        return $coalesce ? "COALESCE(" . $sql . ", '[]'::jsonb)" : $sql;
    }

    public function nullGuard(string $column, string $expression): string
    {
        return 'CASE WHEN ' . $column . ' IS NULL THEN NULL ELSE ' . $expression . ' END';
    }

    public function jsonParameter(string $placeholder, ParameterType $type): string
    {
        $cast = match ($type) {
            ParameterType::INTEGER => 'bigint',
            ParameterType::FLOAT => 'double precision',
            ParameterType::BOOLEAN => 'boolean',
            default => 'text',
        };
        return 'CAST(' . $placeholder . ' AS ' . $cast . ')';
    }
}
