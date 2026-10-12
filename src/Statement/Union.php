<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Statement;

use InvalidArgumentException;
use WilyCode\Database\Query\Clause\LimitOffset;
use WilyCode\Database\Query\Clause\OrderByClause;
use WilyCode\Database\Query\CompiledQuery;
use WilyCode\Database\Query\Enum\OrderDirection;
use WilyCode\Database\Query\ParameterBag;
use WilyCode\Database\Query\Support\ParameterImporter;

final class Union implements QueryInterface
{
    /** @var list<QueryInterface> */
    private array $queries = [];

    private OrderByClause $orderBy;
    private LimitOffset $limitOffset;

    public function __construct()
    {
        $this->orderBy = new OrderByClause();
        $this->limitOffset = new LimitOffset();
    }

    public function add(QueryInterface $query): self
    {
        $this->queries[] = $query;
        return $this;
    }

    public function orderBy(string $column, string|OrderDirection $direction = OrderDirection::ASC): self
    {
        $this->orderBy->add(
            $column,
            is_string($direction) ? OrderDirection::fromString($direction) : $direction,
        );

        return $this;
    }

    public function limit(int $limit): self
    {
        $this->limitOffset->limit($limit);
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->limitOffset->offset($offset);
        return $this;
    }

    public function pagination(int $page, int $perPage): self
    {
        $this->limitOffset->pagination($page, $perPage);
        return $this;
    }

    public function compile(): CompiledQuery
    {
        if ($this->queries === []) {
            throw new InvalidArgumentException('UNION must contain at least one query.');
        }

        $bag = new ParameterBag();
        $parts = [];

        foreach ($this->queries as $index => $query) {
            $sql = ParameterImporter::import(
                $query->compile(),
                $bag,
                'union_' . ($index + 1),
            );
            $parts[] = '(' . $sql . ')';
        }

        $sql = implode(' UNION ', $parts);
        $orderBy = trim($this->orderBy->toSql());
        $limitOffset = trim($this->limitOffset->toSql());

        if ($orderBy !== '') {
            $sql .= ' ' . $orderBy;
        }
        if ($limitOffset !== '') {
            $sql .= ' ' . $limitOffset;
        }

        return new CompiledQuery($sql, $bag->getParameters(), $bag->getTypes());
    }

}
