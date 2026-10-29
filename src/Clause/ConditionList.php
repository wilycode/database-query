<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use WilyCode\Database\Query\Expression\Expression;
use WilyCode\Database\Query\ParameterBag;

final class ConditionList
{
    /** @var list<array{string|Expression|self, string}> */
    private array $items = [];

    public function addExpression(string|Expression $expression, string $logicalOperator): void
    {
        $this->items[] = [$expression, $logicalOperator];
    }

    public function addGroup(self $group, string $logicalOperator): void
    {
        $this->items[] = [$group, $logicalOperator];
    }

    public function toSql(?ParameterBag $bag = null): ?string
    {
        $bag ??= new ParameterBag();
        $parts = [];
        foreach ($this->items as [$item, $logical]) {
            if ($item instanceof self) {
                $sql = $item->toSql($bag);
                if ($sql === null) { continue; }
                $sql = '(' . $sql . ')';
            } else {
                $sql = $item instanceof Expression ? $item->compile($bag) : $item;
            }
            $parts[] = ($parts === [] ? '' : $logical . ' ') . $sql;
        }
        return $parts === [] ? null : implode(' ', $parts);
    }
}
