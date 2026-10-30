<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Dialect;

use WilyCode\Database\Query\Enum\ParameterType;

final class SqliteDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function paginate(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
    {
        if ($limit !== null || $offset !== null) {
            $sql .= ' LIMIT ' . ($limit ?? -1);
        }
        return $sql . ($offset !== null ? ' OFFSET ' . $offset : '');
    }

    public function unionOperand(string $sql): string
    {
        return 'SELECT * FROM (' . $sql . ')';
    }

    public function jsonObject(array $parts): string
    {
        return 'JSON_OBJECT(' . implode(', ', $parts) . ')';
    }

    public function jsonArray(array $items): string
    {
        return 'JSON_ARRAY(' . implode(', ', $items) . ')';
    }

    public function jsonAggregate(string $item, bool $distinct, bool $coalesce): string
    {
        $sql = 'JSON_GROUP_ARRAY(' . ($distinct ? 'DISTINCT ' : '') . $item . ')';
        // SQLite returns [] for zero input rows; normalize to the API's NULL option.
        return $coalesce ? $sql : 'CASE WHEN COUNT(*) = 0 THEN NULL ELSE ' . $sql . ' END';
    }

    public function nullGuard(string $column, string $expression): string
    {
        return 'CASE WHEN ' . $column . ' IS NULL THEN NULL ELSE ' . $expression . ' END';
    }

    public function jsonParameter(string $placeholder, ParameterType $type): string
    {
        return match ($type) {
            ParameterType::BOOLEAN => "JSON(CASE WHEN " . $placeholder . " THEN 'true' ELSE 'false' END)",
            ParameterType::FLOAT => 'CAST(' . $placeholder . ' AS REAL)',
            default => $placeholder,
        };
    }

    public function jsonResult(string $sql): string
    {
        return 'JSON(' . $sql . ')';
    }
}
