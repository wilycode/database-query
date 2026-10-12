<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use WilyCode\Database\Query\Enum\ComparisonOperator;
use WilyCode\Database\Query\Enum\LogicalOperator;
use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\Expression\RawExpression;
use WilyCode\Database\Query\ParameterBag;

final class ConditionGroup
{
    public function __construct(
        private readonly ParameterBag $parameters,
        private readonly ConditionList $list
    ) {}

    public function where(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): self {
        $op = is_string($comparisonOperator)
            ? ComparisonOperator::fromString($comparisonOperator)
            : $comparisonOperator;

        $bool = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $expr = ConditionExpressionBuilder::build(
            $this->parameters,
            $column,
            $value,
            $op,
        );

        $this->list->addExpression($expr, $bool->value);

        return $this;
    }

    /**
     * Add a condition comparing two columns (no parameter binding).
     */
    public function whereColumn(
        string $leftColumn,
        string $rightColumn,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): self {
        $op = is_string($comparisonOperator)
            ? ComparisonOperator::fromString($comparisonOperator)
            : $comparisonOperator;

        $bool = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $fragment = sprintf('%s %s %s', $leftColumn, $op->value, $rightColumn);
        $this->list->addExpression($fragment, $bool->value);

        return $this;
    }

    /**
     * Add a condition from an Expression (may bind parameters).
     */
    public function whereExp(Expression $expression, string|LogicalOperator $logicalOperator = LogicalOperator::AND): self
    {
        $bool = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $fragment = $expression->compile($this->parameters);
        $this->list->addExpression($fragment, $bool->value);

        return $this;
    }

    public function andWhere(string $column, mixed $value, string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS): self
    {
        return $this->where($column, $value, $comparisonOperator, LogicalOperator::AND);
    }

    public function orWhere(string $column, mixed $value, string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS): self
    {
        return $this->where($column, $value, $comparisonOperator, LogicalOperator::OR);
    }
    /** @param array<int|string, mixed> $parameters */
    public function whereRaw(
        string $condition,
        array $parameters = [],
        string|LogicalOperator $logicalOperator = LogicalOperator::AND
    ): self
    {
        return $this->whereExp(
            RawExpression::raw($condition, $parameters),
            $logicalOperator
        );
    }


    /** @param array<int|string, mixed> $parameters */
    public function andWhereRaw(string $condition, array $parameters = []): self
    {
        return $this->whereRaw($condition, $parameters, LogicalOperator::AND);
    }

    /** @param array<int|string, mixed> $parameters */
    public function orWhereRaw(string $condition, array $parameters = []): self
    {
        return $this->whereRaw($condition, $parameters, LogicalOperator::OR);
    }

    /** Nested groups: ( ... AND ( ... OR ... ) ) */
    public function whereGroup(callable $callback, string|LogicalOperator $logicalOperator = LogicalOperator::AND): self
    {
        $bool = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $nested = new ConditionList();
        $callback(new self($this->parameters, $nested));

        $this->list->addGroup($nested, $bool->value);

        return $this;
    }
}
