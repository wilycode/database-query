<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use WilyCode\Database\Query\Enum\ComparisonOperator;
use WilyCode\Database\Query\Enum\LogicalOperator;
use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\Expression\RawExpression;
use WilyCode\Database\Query\ParameterBag;

abstract class WhereClause
{
    protected ParameterBag $parameters;
    protected ConditionList $whereList;

    public function __construct()
    {
        $this->parameters = new ParameterBag();
        $this->whereList = new ConditionList();
    }

    protected function whereGroupBuilder(): ConditionGroup
    {
        return new ConditionGroup($this->parameters, $this->whereList);
    }

    public function where(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): static {
        $this->whereGroupBuilder()->where($column, $value, $comparisonOperator, $logicalOperator);
        return $this;
    }

    public function andWhere(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS
    ): static {
        return $this->where($column, $value, $comparisonOperator, LogicalOperator::AND);
    }

    public function orWhere(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS
    ): static {
        return $this->where($column, $value, $comparisonOperator, LogicalOperator::OR);
    }

    public function whereColumn(
        string $leftColumn,
        string $rightColumn,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): static {
        $this->whereGroupBuilder()->whereColumn($leftColumn, $rightColumn, $comparisonOperator, $logicalOperator);
        return $this;
    }

    public function whereExp(Expression $expression, string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        $this->whereGroupBuilder()->whereExp($expression, $logicalOperator);
        return $this;
    }

    /**
     * Raw WHERE condition. Supports ?, :name and IN (?) arrays through ParameterBag::process.
     *
     * @param array<int|string, mixed> $parameters
     */
    public function whereRaw(string $condition, array $parameters = [], string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        return $this->whereExp(RawExpression::raw($condition, $parameters), $logicalOperator);
    }

    /** @param array<int|string, mixed> $parameters */
    public function andWhereRaw(string $condition, array $parameters = []): static
    {
        return $this->whereRaw($condition, $parameters, LogicalOperator::AND);
    }

    /** @param array<int|string, mixed> $parameters */
    public function orWhereRaw(string $condition, array $parameters = []): static
    {
        return $this->whereRaw($condition, $parameters, LogicalOperator::OR);
    }

    /**
     * Nested group in WHERE: (...)
     *
     * Example:
     *   ->whereGroup(fn($g) => $g->where('a', 1)->orWhere('b', 2))
     */
    public function whereGroup(callable $callback, string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        $this->whereGroupBuilder()->whereGroup($callback, $logicalOperator);
        return $this;
    }

    public function andWhereGroup(callable $callback): static
    {
        return $this->whereGroup($callback, LogicalOperator::AND);
    }

    public function orWhereGroup(callable $callback): static
    {
        return $this->whereGroup($callback, LogicalOperator::OR);
    }

    protected function renderWheres(ParameterBag $bag): ?string
    {
        return $this->whereList->toSql($bag);
    }

}
