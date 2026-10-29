<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use WilyCode\Database\Query\Enum\ComparisonOperator;
use WilyCode\Database\Query\Enum\LogicalOperator;
use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\Expression\RawExpression;
use WilyCode\Database\Query\ParameterBag;

final class HavingGroup
{
    public function __construct(
        private readonly ParameterBag $parameters,
        private readonly ConditionList $list,
    ) {}

    public function having(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND,
    ): self {
        $operator = is_string($comparisonOperator)
            ? ComparisonOperator::fromString($comparisonOperator)
            : $comparisonOperator;
        $logical = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $this->list->addExpression(
            ConditionExpressionBuilder::build($this->parameters, $column, $value, $operator),
            $logical->value,
        );

        return $this;
    }

    public function andHaving(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
    ): self {
        return $this->having($column, $value, $comparisonOperator, LogicalOperator::AND);
    }

    public function orHaving(
        string $column,
        mixed $value,
        string|ComparisonOperator $comparisonOperator = ComparisonOperator::EQUALS,
    ): self {
        return $this->having($column, $value, $comparisonOperator, LogicalOperator::OR);
    }

    public function havingExp(
        Expression $expression,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND,
    ): self {
        $logical = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $this->list->addExpression(
            $expression instanceof RawExpression ? $expression->compile($this->parameters) : $expression,
            $logical->value,
        );

        return $this;
    }

    /** @param array<int|string, mixed> $parameters */
    public function havingRaw(
        string $condition,
        array $parameters = [],
        string|LogicalOperator $logicalOperator = LogicalOperator::AND,
    ): self {
        return $this->havingExp(
            RawExpression::raw($condition, $parameters),
            $logicalOperator,
        );
    }

    /** @param array<int|string, mixed> $parameters */
    public function andHavingRaw(string $condition, array $parameters = []): self
    {
        return $this->havingRaw($condition, $parameters, LogicalOperator::AND);
    }

    /** @param array<int|string, mixed> $parameters */
    public function orHavingRaw(string $condition, array $parameters = []): self
    {
        return $this->havingRaw($condition, $parameters, LogicalOperator::OR);
    }

    public function havingGroup(
        callable $callback,
        string|LogicalOperator $logicalOperator = LogicalOperator::AND,
    ): self {
        $logical = is_string($logicalOperator)
            ? LogicalOperator::fromString($logicalOperator)
            : $logicalOperator;

        $nested = new ConditionList();
        $callback(new self($this->parameters, $nested));
        $this->list->addGroup($nested, $logical->value);

        return $this;
    }
}
