<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Enum;

enum OrderDirection: string
{
    case ASC = 'ASC';
    case DESC = 'DESC';
    case NULLS_FIRST = 'NULLS FIRST';
    case NULLS_LAST = 'NULLS LAST';

    public static function fromString(string $value): self
    {
        return match (strtoupper(trim($value))) {
            'ASC' => self::ASC,
            'DESC' => self::DESC,
            'NULLS FIRST' => self::NULLS_FIRST,
            'NULLS LAST' => self::NULLS_LAST,
            default => throw new \ValueError("Invalid order direction: {$value}")
        };
    }
}