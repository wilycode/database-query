<?php

declare(strict_types=1);

namespace WilyCode\Database\Query\Enum;

enum LogicalOperator: string
{
    case EMPTY = '';
    case AND = 'AND';
    case OR = 'OR';

    public static function fromString(string $operator): self
    {
        return match (strtoupper(trim($operator))) {
            'AND' => self::AND,
            'OR' => self::OR,
            '' => self::EMPTY,
            default => throw new \InvalidArgumentException("Invalid logical operator: $operator")
        };
    }
} 