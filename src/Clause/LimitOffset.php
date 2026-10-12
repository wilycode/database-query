<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use InvalidArgumentException;

final class LimitOffset
{
    private ?int $limit = null;
    private ?int $offset = null;

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Limit cannot be negative.');
        }

        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Offset cannot be negative.');
        }

        $this->offset = $offset;
        return $this;
    }

    public function pagination(int $page, int $perPage): self
    {
        if ($page < 1) {
            throw new InvalidArgumentException('Page must be at least 1.');
        }
        if ($perPage < 1) {
            throw new InvalidArgumentException('Items per page must be at least 1.');
        }

        return $this->limit($perPage)->offset(($page - 1) * $perPage);
    }

    public function toSql(): string
    {
        $parts = [];
        if ($this->limit !== null) {
            $parts[] = 'LIMIT ' . $this->limit;
        }
        if ($this->offset !== null) {
            $parts[] = 'OFFSET ' . $this->offset;
        }

        return implode(' ', $parts);
    }
}
