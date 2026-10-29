<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Dialect;

use LogicException;
use WilyCode\Database\Query\Enum\ParameterType;

final class MySqlDialect extends AbstractDialect
{
    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function paginate(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string
    {
        if ($limit !== null) {
            $sql .= ' LIMIT ' . $limit;
        } elseif ($offset !== null) {
            $sql .= ' LIMIT 18446744073709551615';
        }
        return $sql . ($offset !== null ? ' OFFSET ' . $offset : '');
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
        if ($distinct) {
            throw new LogicException('MySQL JSON_ARRAYAGG does not support DISTINCT.');
        }
        $sql = 'JSON_ARRAYAGG(' . $item . ')';
        return $coalesce ? 'COALESCE(' . $sql . ', JSON_ARRAY())' : $sql;
    }

    public function jsonParameter(string $placeholder, ParameterType $type): string
    {
        return match ($type) {
            ParameterType::BOOLEAN => "JSON_EXTRACT(IF(" . $placeholder . ", 'true', 'false'), '$')",
            ParameterType::FLOAT => '(' . $placeholder . ' + 0e0)',
            default => $placeholder,
        };
    }

    public function nullGuard(string $column, string $expression): string
    {
        return 'IF(' . $column . ' IS NULL, NULL, ' . $expression . ')';
    }
}
