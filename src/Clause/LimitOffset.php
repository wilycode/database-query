<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Clause;

use InvalidArgumentException;
use WilyCode\Database\Query\Dialect\DialectInterface;
use WilyCode\Database\Query\Query;

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

    public function apply(string $sql, DialectInterface $dialect, bool $hasOrderBy): string
    {
        return $dialect->paginate($sql, $this->limit, $this->offset, $hasOrderBy);
    }

    public function toSql(): string
    {
        return ltrim($this->apply('', Query::dialectDefault(), false));
    }
}
