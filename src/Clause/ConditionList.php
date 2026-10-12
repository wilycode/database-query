<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

final class ConditionList
{
    /** @var string[] */
    private array $items = [];

    public function addExpression(string $expression, string $logicalOperator): void
    {
        if ($this->items === []) {
            $this->items[] = $expression;
            return;
        }

        $this->items[] = $logicalOperator . ' ' . $expression;
    }

    public function addGroup(self $group, string $logicalOperator): void
    {
        $sql = $group->toSql();
        if ($sql === null) {
            return;
        }

        $this->addExpression('(' . $sql . ')', $logicalOperator);
    }

    public function toSql(): ?string
    {
        if ($this->items === []) {
            return null;
        }

        return implode(' ', $this->items);
    }
}
