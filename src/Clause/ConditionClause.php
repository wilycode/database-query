<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use WilyCode\Database\Query\Enum\ComparisonOperator;
use WilyCode\Database\Query\Enum\LogicalOperator;
use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\Expression\RawExpression;

/**
 * Adds HAVING support on top of WHERE (used by SELECT statements).
 */
abstract class ConditionClause extends WhereClause
{
    protected ConditionList $havingList;

    public function __construct()
    {
        parent::__construct();
        $this->havingList = new ConditionList();
    }

    protected function havingGroupBuilder(): HavingGroup
    {
        return new HavingGroup($this->parameters, $this->havingList);
    }

    public function having(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): static {
        $this->havingGroupBuilder()->having($column, $value, $comparisonOperator, $logicalOperator);
        return $this;
    }

    public function andHaving(string $column, mixed $value, string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS): static
    {
        return $this->having($column, $value, $comparisonOperator, LogicalOperator::AND);
    }

    public function orHaving(string $column, mixed $value, string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS): static
    {
        return $this->having($column, $value, $comparisonOperator, LogicalOperator::OR);
    }

    /**
     * Add a compiled expression to HAVING.
     */
    public function havingExp(Expression $expression, string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        $this->havingGroupBuilder()->havingExp($expression, $logicalOperator);
        return $this;
    }

    /**
     * Raw HAVING condition. Supports ?, :name and IN (?) arrays through RawExpression.
     *
     * @param array<int|string, mixed> $parameters
     */
    public function havingRaw(string $condition, array $parameters = [], string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        return $this->havingExp(RawExpression::raw($condition, $parameters), $logicalOperator);
    }

    /** @param array<int|string, mixed> $parameters */
    public function andHavingRaw(string $condition, array $parameters = []): static
    {
        return $this->havingRaw($condition, $parameters, LogicalOperator::AND);
    }

    /** @param array<int|string, mixed> $parameters */
    public function orHavingRaw(string $condition, array $parameters = []): static
    {
        return $this->havingRaw($condition, $parameters, LogicalOperator::OR);
    }

    /**
     * Nested group in HAVING: (...)
     */
    public function havingGroup(callable $callback, string|LogicalOperator $logicalOperator = LogicalOperator::AND): static
    {
        $this->havingGroupBuilder()->havingGroup($callback, $logicalOperator);
        return $this;
    }

    public function andHavingGroup(callable $callback): static
    {
        return $this->havingGroup($callback, LogicalOperator::AND);
    }

    public function orHavingGroup(callable $callback): static
    {
        return $this->havingGroup($callback, LogicalOperator::OR);
    }

    protected function renderHavings(): ?string
    {
        return $this->havingList->toSql();
    }

    protected function buildHaving(): string
    {
        $h = $this->renderHavings();
        return $h ? 'HAVING ' . $h : '';
    }
}
