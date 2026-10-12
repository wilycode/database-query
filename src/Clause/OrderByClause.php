<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use InvalidArgumentException;
use WilyCode\Database\Query\Enum\OrderDirection;

final class OrderByClause
{
    /** @var list<array{0:string,1:OrderDirection}> */
    private array $orders = [];

    public function add(string $column, OrderDirection $direction = OrderDirection::ASC): self
    {
        $column = trim($column);
        if ($column === '') {
            throw new InvalidArgumentException('ORDER BY column cannot be empty.');
        }

        $this->orders[] = [$column, $direction];
        return $this;
    }

    public function toSql(): string
    {
        if ($this->orders === []) {
            return '';
        }

        $orders = array_map(
            static fn (array $order): string => $order[0] . ' ' . $order[1]->value,
            $this->orders,
        );

        return 'ORDER BY ' . implode(', ', $orders);
    }
}
