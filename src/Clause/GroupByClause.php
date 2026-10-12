<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use InvalidArgumentException;

final class GroupByClause
{
    /** @var list<string> */
    private array $columns = [];

    /** @param string|list<string> $columns */
    public function add(string|array $columns): self
    {
        foreach ((array) $columns as $column) {
            $column = trim((string) $column);
            if ($column === '') {
                throw new InvalidArgumentException('GROUP BY column cannot be empty.');
            }
            $this->columns[] = $column;
        }

        return $this;
    }

    public function toSql(): string
    {
        return $this->columns === []
            ? ''
            : 'GROUP BY ' . implode(', ', $this->columns);
    }
}
