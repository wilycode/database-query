<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Dialect;

use WilyCode\Database\Query\Enum\ParameterType;

/** SQL generation only: connections, binding and generated IDs belong to drivers. */
interface DialectInterface
{
    public function name(): string;
    /** Quote a single identifier (not a dotted path or SQL expression). */
    public function quoteIdentifier(string $identifier): string;
    public function tableAlias(string $table, string $alias): string;
    /** Receives a complete SELECT/UNION, allowing suffixes, TOP or wrappers. */
    public function paginate(string $sql, ?int $limit, ?int $offset, bool $hasOrderBy): string;
    /** @param list<string> $columns @param list<list<string>> $rows Bound SQL values. */
    public function insert(string $table, array $columns, array $rows): string;
    public function unionOperand(string $sql): string;
    /** @param list<string> $parts Alternating SQL keys and values. */
    public function jsonObject(array $parts): string;
    /** @param list<string> $items */
    public function jsonArray(array $items): string;
    public function jsonAggregate(string $item, bool $distinct, bool $coalesce): string;
    public function nullGuard(string $column, string $expression): string;
    public function jsonParameter(string $placeholder, ParameterType $type): string;
    /** Restore JSON typing when a JSON expression crosses a subquery boundary. */
    public function jsonResult(string $sql): string;
}
